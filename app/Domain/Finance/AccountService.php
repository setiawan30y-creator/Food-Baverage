<?php

namespace App\Domain\Finance;

use App\Domain\Shared\Audit;
use App\Models\AccountMutation;
use App\Models\Payment;
use App\Models\PaymentAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saldo rekening (bank, QRIS, e-wallet, kas) =
 *   saldo awal + pembayaran pesanan yang masuk ke rekening + mutasi masuk − mutasi keluar.
 * Pembayaran yang direfund tidak dihitung (uang dikembalikan ke pelanggan).
 * @2026-Alifiandra
 */
class AccountService
{
    /** Tentukan rekening tujuan pembayaran. Satu rekening aktif → otomatis; lebih dari satu → wajib dipilih. */
    public function resolveForPayment(int $branchId, string $method, ?int $accountId): ?int
    {
        $accounts = PaymentAccount::usableAt($branchId)->where('method', $method)->pluck('id');
        if ($accountId) {
            if (! $accounts->contains($accountId)) {
                throw new \DomainException('Rekening tujuan tidak cocok dengan metode pembayaran atau cabang ini.');
            }

            return $accountId;
        }
        if ($accounts->count() > 1) {
            throw new \DomainException('Pilih rekening tujuan pembayaran ('.config('fnb.payment_methods.'.$method, $method).').');
        }

        return $accounts->first();
    }

    /** Rekening aktif per metode untuk cabang (dipakai form pembayaran). */
    public function optionsFor(int $branchId): Collection
    {
        return PaymentAccount::usableAt($branchId)->orderBy('name')->get(['id', 'name', 'method', 'account_number'])
            ->groupBy('method')
            ->map(fn ($g) => $g->map(fn ($a) => ['id' => $a->id, 'label' => $a->label()])->values());
    }

    /**
     * Ringkasan per rekening untuk periode: saldo awal periode, masuk (penjualan & mutasi), keluar, saldo akhir.
     *
     * @return Collection<int, array>
     */
    public function summary(Collection $accounts, Carbon $from, Carbon $to, ?int $branchId = null): Collection
    {
        $ids = $accounts->pluck('id');
        $paySum = fn ($q) => $q->where('status', 'success')->whereIn('payment_account_id', $ids)
            ->when($branchId, fn ($w) => $w->where('branch_id', $branchId))
            ->groupBy('payment_account_id')->selectRaw('payment_account_id, COUNT(*) as cnt, SUM(amount) as total');
        $before = $paySum(Payment::query()->where('paid_at', '<', $from))->get()->keyBy('payment_account_id');
        $during = $paySum(Payment::query()->whereBetween('paid_at', [$from, $to]))->get()->keyBy('payment_account_id');

        $mutSum = fn ($q) => $q->whereIn('payment_account_id', $ids)
            ->groupBy('payment_account_id', 'direction')->selectRaw('payment_account_id, direction, SUM(amount) as total, COUNT(*) as cnt');
        $mBefore = $mutSum(AccountMutation::query()->whereDate('date', '<', $from->toDateString()))->get()->groupBy('payment_account_id');
        $mDuring = $mutSum(AccountMutation::query()->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString()))->get()->groupBy('payment_account_id');

        $dir = fn ($rows, $id, $d) => (float) (optional(($rows[$id] ?? collect())->firstWhere('direction', $d))->total ?? 0);

        return $accounts->map(function (PaymentAccount $a) use ($before, $during, $mBefore, $mDuring, $dir) {
            $start = (float) $a->opening_balance + (float) ($before[$a->id]->total ?? 0) + $dir($mBefore, $a->id, 'in') - $dir($mBefore, $a->id, 'out');
            $sales = (float) ($during[$a->id]->total ?? 0);
            $in = $dir($mDuring, $a->id, 'in');
            $out = $dir($mDuring, $a->id, 'out');

            return [
                'account' => $a,
                'start' => $start,
                'sales_count' => (int) ($during[$a->id]->cnt ?? 0),
                'sales' => $sales,
                'in' => $in,
                'out' => $out,
                'end' => $start + $sales + $in - $out,
            ];
        });
    }

    /** Mutasi rekening (penjualan + manual) urut waktu, dengan saldo berjalan. */
    public function ledger(PaymentAccount $account, Carbon $from, Carbon $to): array
    {
        $start = $this->summary(collect([$account]), $from, $to)->first()['start'];

        $rows = collect();
        Payment::with('order:id,number,customer_name')->where('payment_account_id', $account->id)->where('status', 'success')
            ->whereBetween('paid_at', [$from, $to])->get()
            ->each(fn ($p) => $rows->push([
                'at' => $p->paid_at, 'type' => 'Penjualan', 'desc' => trim(($p->order?->number ?? '').' '.($p->order?->customer_name ?? '')),
                'ref' => $p->reference, 'in' => (float) $p->amount, 'out' => 0.0, 'order_id' => $p->order_id, 'user' => null,
            ]));
        AccountMutation::with('user:id,name')->where('payment_account_id', $account->id)
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->get()
            ->each(fn ($m) => $rows->push([
                'at' => $m->date->copy()->setTimeFrom($m->created_at), 'type' => $m->categoryLabel(), 'desc' => $m->note,
                'ref' => null, 'in' => $m->direction === 'in' ? (float) $m->amount : 0.0, 'out' => $m->direction === 'out' ? (float) $m->amount : 0.0,
                'order_id' => null, 'user' => $m->user?->name,
            ]));

        $balance = $start;
        $rows = $rows->sortBy('at')->values()->map(function ($r) use (&$balance) {
            $balance += $r['in'] - $r['out'];
            $r['balance'] = $balance;

            return $r;
        });

        return ['start' => $start, 'rows' => $rows, 'end' => $balance];
    }

    public function record(PaymentAccount $account, string $direction, string $category, float $amount, string $date, ?string $note): AccountMutation
    {
        $m = $account->mutations()->create([
            'tenant_id' => $account->tenant_id, 'direction' => $direction, 'category' => $category,
            'amount' => $amount, 'date' => $date, 'note' => $note, 'user_id' => auth()->id(),
        ]);
        Audit::log('finance.mutation', $account, null, ['direction' => $direction, 'category' => $category, 'amount' => $amount, 'date' => $date], $note);

        return $m;
    }

    public function transfer(PaymentAccount $from, PaymentAccount $to, float $amount, string $date, ?string $note): void
    {
        if ($from->is($to)) {
            throw new \DomainException('Rekening asal dan tujuan tidak boleh sama.');
        }
        DB::transaction(function () use ($from, $to, $amount, $date, $note) {
            $group = (string) Str::uuid();
            foreach ([[$from, 'out', 'ke '.$to->name], [$to, 'in', 'dari '.$from->name]] as [$acc, $dir, $label]) {
                $acc->mutations()->create([
                    'tenant_id' => $acc->tenant_id, 'direction' => $dir, 'category' => 'transfer', 'amount' => $amount, 'date' => $date,
                    'note' => trim('Pindah dana '.$label.($note ? ' — '.$note : '')), 'transfer_group' => $group, 'user_id' => auth()->id(),
                ]);
            }
            Audit::log('finance.transfer', $from, null, ['to' => $to->id, 'amount' => $amount, 'date' => $date], $note);
        });
    }
}
