<?php

namespace App\Http\Controllers;

use App\Domain\Finance\AccountService;
use App\Domain\Shared\Audit;
use App\Models\AccountMutation;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Saldo Kas & Rekening: bank, QRIS, e-wallet (OVO, GoPay, DANA…), kas.
 * Saldo awal, penerimaan penjualan per rekening, mutasi manual & pindah dana.
 * @2026-Alifiandra
 */
class FinanceController extends Controller
{
    public function __construct(protected AccountService $service) {}

    protected function range(Request $request): array
    {
        $from = Carbon::parse($request->query('from', today()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', today()->toDateString()))->endOfDay();

        return [$from, $to];
    }

    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $accounts = PaymentAccount::with('branch')->orderByDesc('is_active')->orderBy('method')->orderBy('name')->get();
        $rows = $this->service->summary($accounts, $from, $to);

        // Semua pembayaran lunas per metode (termasuk yang belum ditautkan ke rekening)
        $byMethod = Payment::query()->where('status', 'success')->whereBetween('paid_at', [$from, $to])
            ->selectRaw('method, COUNT(*) as cnt, SUM(amount) as total, SUM(CASE WHEN payment_account_id IS NULL THEN 1 ELSE 0 END) as unlinked_cnt, SUM(CASE WHEN payment_account_id IS NULL THEN amount ELSE 0 END) as unlinked')
            ->groupBy('method')->get()->keyBy('method');

        if ($request->query('export')) {
            return $this->csv('saldo-rekening.csv', ['Rekening', 'Jenis', 'No. rekening', 'Saldo awal periode', 'Jumlah transaksi', 'Penjualan masuk', 'Mutasi masuk', 'Mutasi keluar', 'Saldo akhir'],
                $rows->map(fn ($r) => [$r['account']->name, $r['account']->methodLabel(), $r['account']->account_number, $r['start'], $r['sales_count'], $r['sales'], $r['in'], $r['out'], $r['end']]));
        }

        return view('finance.index', [
            'rows' => $rows, 'byMethod' => $byMethod, 'from' => $from, 'to' => $to,
            'activeAccounts' => $accounts->where('is_active', true)->values(),
        ]);
    }

    public function create()
    {
        return view('finance.form', ['account' => new PaymentAccount(['method' => 'transfer', 'opening_date' => today(), 'opening_balance' => 0, 'is_active' => true]), 'branches' => Branch::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $account = PaymentAccount::create($this->validated($request));
        Audit::log('finance.account_created', $account, null, $account->only('name', 'method', 'opening_balance'));

        return redirect()->route('finance.index')->with('success', 'Rekening "'.$account->name.'" ditambahkan.');
    }

    public function edit(PaymentAccount $account)
    {
        return view('finance.form', ['account' => $account, 'branches' => Branch::orderBy('name')->get()]);
    }

    public function update(Request $request, PaymentAccount $account)
    {
        $before = $account->only('name', 'method', 'opening_balance', 'opening_date', 'is_active');
        $account->update($this->validated($request, $account));
        Audit::log('finance.account_updated', $account, $before, $account->only('name', 'method', 'opening_balance', 'opening_date', 'is_active'));

        return redirect()->route('finance.show', $account)->with('success', 'Rekening diperbarui.');
    }

    public function show(Request $request, PaymentAccount $account)
    {
        [$from, $to] = $this->range($request);
        $ledger = $this->service->ledger($account, $from, $to);

        if ($request->query('export')) {
            return $this->csv('mutasi-'.\Illuminate\Support\Str::slug($account->name).'.csv', ['Waktu', 'Jenis', 'Keterangan', 'Referensi', 'Masuk', 'Keluar', 'Saldo'],
                $ledger['rows']->map(fn ($r) => [$r['at']->format('Y-m-d H:i'), $r['type'], $r['desc'], $r['ref'], $r['in'], $r['out'], $r['balance']]));
        }

        return view('finance.show', [
            'account' => $account, 'ledger' => $ledger, 'from' => $from, 'to' => $to,
            'others' => PaymentAccount::where('is_active', true)->whereKeyNot($account->id)->orderBy('name')->get(),
        ]);
    }

    public function mutation(Request $request, PaymentAccount $account)
    {
        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'category' => ['required', Rule::in(array_diff(array_keys(AccountMutation::CATEGORIES), ['transfer']))],
            'amount' => ['required', 'numeric', 'min:1'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['required', 'string', 'min:3', 'max:200'],
        ]);
        $this->service->record($account, $data['direction'], $data['category'], (float) $data['amount'], $data['date'], $data['note']);

        return back()->with('success', 'Mutasi dicatat.');
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'from_id' => ['required', 'integer'],
            'to_id' => ['required', 'integer', 'different:from_id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:150'],
        ], ['to_id.different' => 'Rekening tujuan harus berbeda dengan rekening asal.']);
        $from = PaymentAccount::findOrFail($data['from_id']);
        $to = PaymentAccount::findOrFail($data['to_id']);
        $this->service->transfer($from, $to, (float) $data['amount'], $data['date'], $data['note'] ?? null);

        return back()->with('success', 'Pindah dana '.rupiah($data['amount']).' dari '.$from->name.' ke '.$to->name.' dicatat.');
    }

    protected function validated(Request $request, ?PaymentAccount $account = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'method' => ['required', Rule::in(array_keys(config('fnb.payment_methods')))],
            'account_number' => ['nullable', 'string', 'max:60'],
            'holder' => ['nullable', 'string', 'max:100'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', tenancy()->tenantId())],
            'opening_balance' => ['required', 'numeric'],
            'opening_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:200'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['branch_id'] = $data['branch_id'] ?? null;

        return $data;
    }
}
