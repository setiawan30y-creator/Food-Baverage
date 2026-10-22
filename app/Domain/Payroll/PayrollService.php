<?php

namespace App\Domain\Payroll;

use App\Domain\Shared\Audit;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\LeaveRequest;
use App\Models\Order;
use App\Models\PayrollRun;
use App\Models\ShiftTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Payroll: bulanan, harian, per shift, per jam, komisi, borongan.
 * Komponen pajak/BPJS TIDAK di-hardcode — diisi pada kolom potongan sesuai aturan yang berlaku.
 * Payroll yang sudah disetujui terkunci; koreksi lewat payroll run penyesuaian.
 * @2026-Alifiandra
 */
class PayrollService
{
    public function calculate(PayrollRun $run): void
    {
        if ($run->isLocked()) {
            throw new \DomainException('Payroll sudah disetujui dan terkunci.');
        }

        DB::transaction(function () use ($run) {
            $start = $run->period_start->copy()->startOfDay();
            $end = $run->period_end->copy()->endOfDay();
            $existing = $run->items()->get()->keyBy('employee_id');
            $run->items()->delete();

            $employees = Employee::where('status', 'active')
                ->when($run->branch_id, fn ($q) => $q->where('branch_id', $run->branch_id))->get();
            $workingCodes = ShiftTemplate::where('is_working', true)->pluck('code')->all();
            $total = 0;

            foreach ($employees as $emp) {
                $events = AttendanceEvent::where('employee_id', $emp->id)->whereBetween('occurred_at', [$start, $end])->orderBy('occurred_at')->get();
                $daysPresent = $events->where('type', 'in')->map(fn ($e) => $e->occurred_at->toDateString())->unique();
                $hours = $this->workedHours($events);
                $rate = (float) $emp->wage_rate;
                $prev = $existing->get($emp->id);

                switch ($emp->wage_method) {
                    case 'daily':
                        $units = $daysPresent->count();
                        $base = $units * $rate;
                        break;
                    case 'shift':
                        $units = EmployeeSchedule::where('employee_id', $emp->id)->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                            ->whereIn('code', $workingCodes)->get()
                            ->filter(fn ($s) => $daysPresent->contains($s->date->toDateString()))->count();
                        $base = $units * $rate;
                        break;
                    case 'hourly':
                        $units = round($hours, 2);
                        $base = $units * $rate;
                        break;
                    case 'commission': // rate = persen dari penjualan lunas yang ditangani
                        $units = $emp->user_id ? (float) Order::where('cashier_id', $emp->user_id)->where('payment_status', 'paid')
                            ->whereBetween('paid_at', [$start, $end])->sum('total') : 0;
                        $base = $units * $rate / 100;
                        break;
                    case 'piece': // jumlah satuan borongan diinput manual
                        $units = (float) ($prev->units ?? 0);
                        $base = $units * $rate;
                        break;
                    default: // monthly
                        $units = 1;
                        $base = $rate;
                }

                $otHours = (float) LeaveRequest::where('employee_id', $emp->id)->where('type', 'lembur')->where('status', 'approved')
                    ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])->sum('hours');
                $overtime = $otHours * (float) $emp->overtime_rate;

                $advanceDeduction = 0;
                foreach ($emp->advances()->where('status', 'active')->get() as $adv) {
                    $advanceDeduction += min((float) $adv->installment, (float) $adv->remaining);
                }

                $bonus = (float) ($prev->bonus ?? 0);
                $deduction = (float) ($prev->deduction ?? 0);
                $allowance = (float) $emp->allowance;
                $net = $base + $allowance + $overtime + $bonus - $deduction - $advanceDeduction;
                $total += $net;

                $run->items()->create([
                    'employee_id' => $emp->id,
                    'wage_method' => $emp->wage_method,
                    'wage_rate' => $rate,
                    'units' => $units,
                    'base_amount' => round($base, 2),
                    'allowance' => $allowance,
                    'overtime' => round($overtime, 2),
                    'bonus' => $bonus,
                    'deduction' => $deduction,
                    'advance_deduction' => round($advanceDeduction, 2),
                    'net' => round($net, 2),
                    'detail' => [
                        'days_present' => $daysPresent->count(),
                        'hours' => round($hours, 2),
                        'overtime_hours' => $otHours,
                    ],
                ]);
            }

            $run->update(['status' => 'calculated', 'total_net' => round($total, 2)]);
        });
    }

    public function recalcTotals(PayrollRun $run): void
    {
        $total = 0;
        foreach ($run->items as $item) {
            $net = (float) $item->base_amount + (float) $item->allowance + (float) $item->overtime + (float) $item->bonus
                - (float) $item->deduction - (float) $item->advance_deduction;
            $item->update(['net' => round($net, 2)]);
            $total += $net;
        }
        $run->update(['total_net' => round($total, 2)]);
    }

    public function approve(PayrollRun $run): void
    {
        if ($run->status !== 'calculated') {
            throw new \DomainException('Payroll harus berstatus Dihitung sebelum disetujui.');
        }
        $run->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => now()]);
        Audit::log('payroll.approved', $run, null, ['total_net' => $run->total_net]);
    }

    public function markPaid(PayrollRun $run): void
    {
        if ($run->status !== 'approved') {
            throw new \DomainException('Payroll harus disetujui sebelum dibayar.');
        }
        DB::transaction(function () use ($run) {
            foreach ($run->items()->with('employee.advances')->get() as $item) {
                $toDeduct = (float) $item->advance_deduction;
                foreach ($item->employee->advances->where('status', 'active') as $adv) {
                    if ($toDeduct <= 0) {
                        break;
                    }
                    $cut = min($toDeduct, (float) $adv->remaining, (float) $adv->installment);
                    $remaining = round((float) $adv->remaining - $cut, 2);
                    $adv->update(['remaining' => $remaining, 'status' => $remaining <= 0 ? 'settled' : 'active']);
                    $toDeduct -= $cut;
                }
            }
            $run->update(['status' => 'paid', 'paid_at' => now()]);
            Audit::log('payroll.paid', $run, null, ['total_net' => $run->total_net]);
        });
    }

    protected function workedHours($events): float
    {
        $hours = 0;
        $in = null;
        foreach ($events as $e) {
            if ($e->type === 'in') {
                $in = $e->occurred_at;
            } elseif ($e->type === 'out' && $in) {
                $hours += $in->diffInMinutes($e->occurred_at) / 60;
                $in = null;
            }
        }

        return $hours;
    }
}
