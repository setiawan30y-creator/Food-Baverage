<?php

namespace App\Http\Controllers;

use App\Domain\Payroll\PayrollService;
use App\Domain\Shared\Audit;
use App\Domain\Shared\SequenceService;
use App\Models\Branch;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Payroll: Draft → Dihitung → Disetujui (terkunci) → Dibayar. Slip privat. @2026-Alifiandra */
class PayrollController extends Controller
{
    public function index()
    {
        return view('payroll.index', [
            'runs' => PayrollRun::latest('period_start')->paginate(20),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, SequenceService $seq, PayrollService $payroll)
    {
        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', tenancy()->tenantId())],
        ]);
        $run = PayrollRun::create($data + [
            'number' => $seq->next(tenancy()->tenantId(), null, 'payroll', 'PAY', 'Ym', 2),
            'created_by' => auth()->id(),
            'status' => 'draft',
        ]);
        $payroll->calculate($run);

        return redirect()->route('payroll.show', $run)->with('success', 'Payroll dihitung. Tinjau sebelum disetujui.');
    }

    public function show(PayrollRun $run)
    {
        $run->load('items.employee', 'approver');

        return view('payroll.show', compact('run'));
    }

    public function calculate(PayrollRun $run, PayrollService $payroll)
    {
        $payroll->calculate($run);

        return back()->with('success', 'Payroll dihitung ulang dari absensi, jadwal & lembur terbaru.');
    }

    public function updateItem(Request $request, PayrollItem $item, PayrollService $payroll)
    {
        $run = $item->run;
        abort_unless($run->tenant_id === tenancy()->tenantId(), 404);
        abort_if($run->isLocked(), 422, 'Payroll terkunci.');
        $data = $request->validate([
            'units' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'deduction' => ['nullable', 'numeric', 'min:0'],
        ]);
        $update = ['bonus' => $data['bonus'] ?? 0, 'deduction' => $data['deduction'] ?? 0];
        if ($item->wage_method === 'piece' && isset($data['units'])) {
            $update['units'] = $data['units'];
            $update['base_amount'] = round($data['units'] * (float) $item->wage_rate, 2);
        }
        $item->update($update);
        $payroll->recalcTotals($run->fresh('items'));

        return back()->with('success', 'Baris payroll diperbarui.');
    }

    public function approve(PayrollRun $run, PayrollService $payroll)
    {
        $payroll->approve($run);

        return back()->with('success', 'Payroll disetujui dan terkunci.');
    }

    public function pay(PayrollRun $run, PayrollService $payroll)
    {
        $payroll->markPaid($run);

        return back()->with('success', 'Payroll ditandai dibayar; cicilan kasbon dipotong.');
    }

    /** Slip gaji: hanya pihak berwenang (payroll) atau karyawan bersangkutan. */
    public function payslip(Request $request, PayrollItem $item)
    {
        $item->load('employee', 'run');
        abort_unless($item->run->tenant_id === tenancy()->tenantId(), 404);
        $user = $request->user();
        abort_unless($user->hasAbility('payroll') || $item->employee->user_id === $user->id, 403);
        abort_unless($item->run->isLocked() || $user->hasAbility('payroll'), 403, 'Slip tersedia setelah payroll disetujui.');
        Audit::log('payroll.payslip_viewed', $item->run, null, ['employee' => $item->employee->name]);

        return view('payroll.payslip', compact('item'));
    }

    public function mine(Request $request)
    {
        $employee = \App\Models\Employee::where('user_id', $request->user()->id)->first();
        $items = $employee ? PayrollItem::where('employee_id', $employee->id)
            ->whereHas('run', fn ($q) => $q->whereIn('status', ['approved', 'paid']))->with('run')->latest()->get() : collect();

        return view('payroll.mine', compact('items', 'employee'));
    }
}
