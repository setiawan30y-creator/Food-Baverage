<?php

namespace App\Domain\CashClosing;

use App\Domain\Shared\Audit;
use App\Domain\Shared\SequenceService;
use App\Models\CashClosing;
use App\Models\CashierShift;
use Illuminate\Support\Facades\DB;

/**
 * Closing kasir: kas seharusnya = saldo awal + penjualan tunai + penerimaan lain
 * - pengeluaran - setoran. Non-tunai direkonsiliasi terpisah dari uang laci.
 * @2026-Alifiandra
 */
class ClosingService
{
    public function __construct(protected SequenceService $sequence) {}

    public function summary(CashierShift $shift): array
    {
        $payments = $shift->payments()->where('status', 'success')->get();
        $cashSales = (float) $payments->where('method', 'cash')->sum('amount');
        $nonCash = $payments->where('method', '!=', 'cash')->groupBy('method')->map(fn ($g) => (float) $g->sum('amount'))->all();

        $moves = $shift->cashMovements()->get();
        $income = (float) $moves->where('type', 'income')->sum('amount');
        $expense = (float) $moves->where('type', 'expense')->sum('amount');
        $deposit = (float) $moves->where('type', 'deposit')->sum('amount');
        $opening = (float) $shift->opening_cash;

        return [
            'opening_cash' => $opening,
            'cash_sales' => $cashSales,
            'other_income' => $income,
            'expenses' => $expense,
            'deposits' => $deposit,
            'expected_cash' => $opening + $cashSales + $income - $expense - $deposit,
            'non_cash' => $nonCash,
            'non_cash_total' => array_sum($nonCash),
            'order_count' => $payments->pluck('order_id')->unique()->count(),
        ];
    }

    /**
     * @param  array<int,int>  $denominations  pecahan => jumlah lembar/keping
     */
    public function finalize(CashierShift $shift, string $method, float $countedTotal, array $denominations, ?string $reason): CashClosing
    {
        if ($shift->status !== 'open') {
            throw new \DomainException('Shift kasir sudah ditutup.');
        }

        return DB::transaction(function () use ($shift, $method, $countedTotal, $denominations, $reason) {
            $s = $this->summary($shift);
            $counted = $countedTotal;
            $denomRows = [];
            if ($method === 'denomination') {
                $counted = 0;
                foreach ($denominations as $value => $qty) {
                    $qty = (int) $qty;
                    if ($qty > 0) {
                        $denomRows[] = ['denomination' => (int) $value, 'qty' => $qty, 'subtotal' => (int) $value * $qty];
                        $counted += (int) $value * $qty;
                    }
                }
            }
            $diff = round($counted - $s['expected_cash'], 2);
            if (abs($diff) > 0.009 && ! trim((string) $reason)) {
                throw new \DomainException('Ada selisih kas. Alasan wajib diisi.');
            }

            $closing = CashClosing::create([
                'tenant_id' => $shift->tenant_id,
                'branch_id' => $shift->branch_id,
                'cashier_shift_id' => $shift->id,
                'user_id' => auth()->id(),
                'number' => $this->sequence->next($shift->tenant_id, $shift->branch_id, 'closing', 'CLS'),
                'opening_cash' => $s['opening_cash'],
                'cash_sales' => $s['cash_sales'],
                'other_income' => $s['other_income'],
                'expenses' => $s['expenses'],
                'deposits' => $s['deposits'],
                'expected_cash' => $s['expected_cash'],
                'count_method' => $method,
                'counted_cash' => $counted,
                'difference' => $diff,
                'non_cash' => $s['non_cash'],
                'non_cash_total' => $s['non_cash_total'],
                'order_count' => $s['order_count'],
                'reason' => $reason,
                'status' => 'final',
                'approval_status' => abs($diff) > (float) config('fnb.approval.closing_difference') ? 'pending' : 'approved',
                'finalized_at' => now(),
            ]);
            foreach ($denomRows as $row) {
                $closing->denominations()->create($row);
            }
            $shift->update(['status' => 'closed', 'closed_at' => now()]);
            Audit::log('closing.finalized', $closing, null, ['expected' => $s['expected_cash'], 'counted' => $counted, 'difference' => $diff], $reason);

            return $closing;
        });
    }

    public function approve(CashClosing $closing, bool $approved, ?string $note): void
    {
        if ($closing->approval_status !== 'pending') {
            throw new \DomainException('Closing ini sudah diproses.');
        }
        $closing->update([
            'approval_status' => $approved ? 'approved' : 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);
        Audit::log($approved ? 'closing.approved' : 'closing.rejected', $closing, null, null, $note);
    }

    /** Buka ulang: closing lama tetap tersimpan (status reopened), shift dibuka kembali. */
    public function reopen(CashClosing $closing, string $reason): void
    {
        if ($closing->status !== 'final') {
            throw new \DomainException('Closing ini sudah dibuka ulang.');
        }
        DB::transaction(function () use ($closing, $reason) {
            $before = $closing->only(['status', 'counted_cash', 'difference']);
            $closing->update(['status' => 'reopened']);
            $closing->shift->update(['status' => 'open', 'closed_at' => null]);
            Audit::log('closing.reopened', $closing, $before, ['status' => 'reopened'], $reason);
        });
    }
}
