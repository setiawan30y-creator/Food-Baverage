<?php

namespace App\Http\Controllers;

use App\Domain\Shared\Audit;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeAdvance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Manajemen karyawan (data pegawai terpisah dari akun login). @2026-Alifiandra */
class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $employees = Employee::with(['branch', 'user'])
            ->when($request->branch !== 'all', fn ($q) => $q->where(fn ($w) => $w->where('branch_id', $this->branchId())->orWhereNull('branch_id')))
            ->when($request->q, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->where('status', 'active'))
            ->orderBy('name')->get();

        return view('employees.index', compact('employees'));
    }

    public function create()
    {
        return view('employees.form', ['employee' => new Employee(['status' => 'active', 'wage_method' => 'monthly', 'branch_id' => $this->branchId(), 'start_date' => today()]), ...$this->formData()]);
    }

    public function store(Request $request)
    {
        $employee = Employee::create($this->validated($request));
        Audit::log('employee.created', $employee, null, $employee->only(['name', 'position', 'wage_method', 'wage_rate']));

        return redirect()->route('employees.edit', $employee)->with('success', 'Karyawan ditambahkan.');
    }

    public function edit(Employee $employee)
    {
        $employee->load('advances');

        return view('employees.form', compact('employee') + $this->formData());
    }

    public function update(Request $request, Employee $employee)
    {
        $data = $this->validated($request, $employee);
        $before = $employee->only(array_keys($data));
        $employee->update($data);
        Audit::log('employee.updated', $employee, $before, $employee->only(array_keys($data)));

        return back()->with('success', 'Data karyawan disimpan.');
    }

    public function storeAdvance(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'installment' => ['required', 'numeric', 'gt:0', 'lte:amount'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $adv = EmployeeAdvance::create($data + ['employee_id' => $employee->id, 'remaining' => $data['amount']]);
        Audit::log('employee.advance', $adv, null, ['amount' => $data['amount']]);

        return back()->with('success', 'Kasbon dicatat; cicilan dipotong otomatis saat payroll dibayar.');
    }

    protected function formData(): array
    {
        return [
            'branches' => Branch::orderBy('name')->get(),
            'users' => User::where('tenant_id', tenancy()->tenantId())->orderBy('name')->get(['id', 'name', 'email']),
        ];
    }

    protected function validated(Request $request, ?Employee $employee = null): array
    {
        $tenant = tenancy()->tenantId();

        return $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('employees')->where('tenant_id', $tenant)->ignore($employee?->id)],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'position' => ['required', Rule::in(Employee::POSITIONS)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenant)],
            'user_id' => ['nullable', Rule::exists('users', 'id')->where('tenant_id', $tenant)],
            'status' => ['required', 'in:active,inactive,resigned'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'wage_method' => ['required', Rule::in(array_keys(Employee::WAGE_METHODS))],
            'wage_rate' => ['required', 'numeric', 'min:0'],
            'allowance' => ['nullable', 'numeric', 'min:0'],
            'overtime_rate' => ['nullable', 'numeric', 'min:0'],
        ]) + ['allowance' => 0, 'overtime_rate' => 0];
    }
}
