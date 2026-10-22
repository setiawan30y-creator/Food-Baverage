<?php

namespace App\Http\Controllers;

use App\Domain\CashClosing\ClosingService;
use App\Models\CashClosing;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;

/** History Closing: arsip final, filter, persetujuan, buka ulang beraudit, ekspor. @2026-Alifiandra */
class ClosingController extends Controller
{
    protected function query(Request $request)
    {
        $user = $request->user();

        return CashClosing::forBranch()->with(['user', 'approver', 'shift'])
            ->when(! $user->hasAbility('closing.approve'), fn ($q) => $q->where('user_id', $user->id))
            ->when($request->from, fn ($q, $d) => $q->whereDate('finalized_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('finalized_at', '<=', $d))
            ->when($request->user_id, fn ($q, $u) => $q->where('user_id', $u))
            ->when($request->number, fn ($q, $n) => $q->where('number', 'like', "%{$n}%"))
            ->when($request->result === 'match', fn ($q) => $q->where('difference', 0))
            ->when($request->result === 'diff', fn ($q) => $q->where('difference', '!=', 0))
            ->when($request->approval, fn ($q, $a) => $q->where('approval_status', $a))
            ->latest('finalized_at');
    }

    public function index(Request $request)
    {
        $closings = $this->query($request)->paginate(25)->withQueryString();
        $cashiers = User::where('tenant_id', tenancy()->tenantId())->orderBy('name')->get(['id', 'name']);

        return view('closings.index', compact('closings', 'cashiers'));
    }

    public function show(Request $request, CashClosing $closing)
    {
        $this->ensureBranch($closing);
        abort_unless($request->user()->hasAbility('closing.approve') || $closing->user_id === $request->user()->id, 403);
        $closing->load(['denominations', 'user', 'approver', 'shift.cashMovements.user']);
        $payments = Payment::where('cashier_shift_id', $closing->cashier_shift_id)->with('order')->orderBy('paid_at')->get();
        $audits = \App\Models\AuditLog::where('auditable_type', 'CashClosing')->where('auditable_id', $closing->id)->with('user')->latest('id')->get();

        return view('closings.show', compact('closing', 'payments', 'audits'));
    }

    public function approve(Request $request, CashClosing $closing, ClosingService $service)
    {
        $this->ensureBranch($closing);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'note' => ['nullable', 'string', 'max:200']]);
        $service->approve($closing, $data['decision'] === 'approve', $data['note'] ?? null);

        return back()->with('success', 'Keputusan closing disimpan.');
    }

    public function reopen(Request $request, CashClosing $closing, ClosingService $service)
    {
        $this->ensureBranch($closing);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);
        $service->reopen($closing, $data['reason']);

        return back()->with('success', 'Closing dibuka ulang. Kasir dapat melakukan closing kembali; arsip lama tetap tersimpan.');
    }

    public function export(Request $request)
    {
        $rows = $this->query($request)->get()->map(fn ($c) => [
            $c->number, $c->finalized_at->format('Y-m-d H:i'), $c->user?->name, $c->shift?->number,
            $c->opening_cash, $c->cash_sales, $c->other_income, $c->expenses, $c->deposits,
            $c->expected_cash, $c->counted_cash, $c->difference, $c->non_cash_total, $c->status, $c->approval_status, $c->reason,
        ]);

        return $this->csv('history-closing-'.now()->format('Ymd').'.csv', [
            'Nomor', 'Waktu final', 'Kasir', 'Shift', 'Saldo awal', 'Penjualan tunai', 'Penerimaan lain', 'Pengeluaran',
            'Setoran', 'Kas seharusnya', 'Kas fisik', 'Selisih', 'Non-tunai', 'Status', 'Persetujuan', 'Alasan',
        ], $rows);
    }

    public function print(CashClosing $closing)
    {
        $this->ensureBranch($closing);
        $closing->load(['denominations', 'user', 'approver', 'shift', 'branch']);

        return view('closings.print', compact('closing'));
    }
}
