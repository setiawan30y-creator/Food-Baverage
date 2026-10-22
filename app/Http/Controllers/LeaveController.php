<?php

namespace App\Http\Controllers;

use App\Domain\Shared\Audit;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pengajuan izin, sakit, cuti, lembur + persetujuan manajer. @2026-Alifiandra */
class LeaveController extends Controller
{
    public function index(Request $request)
    {
        $manage = $request->user()->hasAbility('attendance.manage');
        $own = Employee::where('user_id', $request->user()->id)->first();
        $leaves = LeaveRequest::with('employee')
            ->when(! $manage, fn ($q) => $q->where('employee_id', $own?->id ?? 0))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(30)->withQueryString();
        $employees = $manage ? Employee::where('status', 'active')->orderBy('name')->get() : collect($own ? [$own] : []);

        return view('attendance.leaves', compact('leaves', 'employees', 'manage'));
    }

    public function store(Request $request)
    {
        $manage = $request->user()->hasAbility('attendance.manage');
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(array_keys(LeaveRequest::TYPES))],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'hours' => ['nullable', 'required_if:type,lembur', 'numeric', 'gt:0', 'max:24'],
            'reason' => ['required', 'string', 'max:255'],
        ]);
        $employee = Employee::findOrFail($data['employee_id']);
        abort_unless($manage || $employee->user_id === $request->user()->id, 403);
        LeaveRequest::create($data + ['status' => 'submitted']);

        return back()->with('success', 'Pengajuan dikirim dan menunggu persetujuan.');
    }

    public function decide(Request $request, LeaveRequest $leave)
    {
        $data = $request->validate(['decision' => ['required', 'in:approved,rejected']]);
        abort_unless($leave->status === 'submitted', 422, 'Pengajuan sudah diproses.');
        $leave->update(['status' => $data['decision'], 'approved_by' => auth()->id(), 'approved_at' => now()]);
        Audit::log('leave.'.$data['decision'], $leave, null, ['type' => $leave->type, 'hours' => $leave->hours]);

        return back()->with('success', 'Pengajuan diperbarui.');
    }

    public function cancel(Request $request, LeaveRequest $leave)
    {
        abort_unless($leave->employee->user_id === $request->user()->id || $request->user()->hasAbility('attendance.manage'), 403);
        abort_unless($leave->status === 'submitted', 422);
        $leave->update(['status' => 'cancelled']);

        return back()->with('success', 'Pengajuan dibatalkan.');
    }
}
