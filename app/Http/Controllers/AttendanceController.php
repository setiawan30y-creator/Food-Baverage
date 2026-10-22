<?php

namespace App\Http\Controllers;

use App\Domain\Attendance\GeoService;
use App\Domain\Shared\Audit;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ShiftTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Absensi GPS: lokasi hanya diambil saat check-in/out (tidak dilacak terus-menerus).
 * GPS bukan bukti anti-manipulasi sempurna — tersedia koreksi manual berizin.
 * @2026-Alifiandra
 */
class AttendanceController extends Controller
{
    public function me(Request $request)
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();
        $today = $employee ? AttendanceEvent::where('employee_id', $employee->id)->whereDate('occurred_at', today())->orderBy('occurred_at')->get() : collect();
        $schedule = $employee ? EmployeeSchedule::where('employee_id', $employee->id)->whereDate('date', today())->first() : null;
        $template = $schedule ? ShiftTemplate::where('code', $schedule->code)->first() : null;
        $history = $employee ? AttendanceEvent::where('employee_id', $employee->id)->latest('occurred_at')->limit(20)->get() : collect();

        return view('attendance.me', compact('employee', 'today', 'schedule', 'template', 'history'));
    }

    public function punch(Request $request)
    {
        $employee = Employee::where('user_id', $request->user()->id)->where('status', 'active')->firstOrFail();
        $data = $request->validate([
            'type' => ['required', 'in:in,out'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $branch = $employee->branch ?? $this->branch();
        $last = AttendanceEvent::where('employee_id', $employee->id)->latest('occurred_at')->first();
        if ($last && $last->type === $data['type'] && $last->occurred_at->isToday()) {
            return back()->with('error', $data['type'] === 'in' ? 'Anda sudah check-in.' : 'Anda sudah check-out.');
        }
        if ($data['type'] === 'out' && (! $last || $last->type !== 'in')) {
            return back()->with('error', 'Belum ada check-in yang bisa ditutup.');
        }

        $distance = ($branch->latitude && $branch->longitude)
            ? (int) round(GeoService::distance((float) $data['latitude'], (float) $data['longitude'], (float) $branch->latitude, (float) $branch->longitude))
            : null;
        $within = $distance !== null && $distance <= $branch->geofence_radius;

        $now = now(); // waktu server, bukan waktu perangkat
        $flag = $within ? null : 'outside_geofence';
        $schedule = EmployeeSchedule::where('employee_id', $employee->id)->whereDate('date', today())->first();
        $tpl = $schedule ? ShiftTemplate::where('code', $schedule->code)->first() : null;
        if ($tpl && $tpl->start_time && $data['type'] === 'in' && $now->format('H:i:s') > Carbon::parse($tpl->start_time)->addMinutes(10)->format('H:i:s')) {
            $flag = $flag ?: 'late';
        }
        if ($tpl && $tpl->end_time && $data['type'] === 'out' && $now->format('H:i:s') < $tpl->end_time) {
            $flag = $flag ?: 'early_leave';
        }

        AttendanceEvent::create([
            'branch_id' => $branch->id,
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'occurred_at' => $now,
            'latitude' => round((float) $data['latitude'], 6),
            'longitude' => round((float) $data['longitude'], 6),
            'accuracy' => $data['accuracy'] ?? null,
            'distance_m' => $distance,
            'within_geofence' => $within,
            'flag' => $flag,
            'note' => $data['note'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $msg = ($data['type'] === 'in' ? 'Check-in' : 'Check-out').' tercatat pukul '.$now->format('H:i').'.';
        if (! $within) {
            $msg .= ' Lokasi di luar radius cabang'.($distance !== null ? " ({$distance} m)" : '').' — ditandai untuk ditinjau manajer.';
        }

        return back()->with($within ? 'success' : 'error', $msg);
    }

    public function index(Request $request)
    {
        $date = $request->query('date', today()->toDateString());
        $events = AttendanceEvent::forBranch()->with('employee')->whereDate('occurred_at', $date)->orderBy('occurred_at')->get();
        $employees = Employee::where('branch_id', $this->branchId())->where('status', 'active')->orderBy('name')->get();
        $scheduled = EmployeeSchedule::forBranch()->whereDate('date', $date)->get()->keyBy('employee_id');
        $working = ShiftTemplate::where('is_working', true)->pluck('code')->all();
        $rows = $employees->map(function ($e) use ($events, $scheduled, $working) {
            $ev = $events->where('employee_id', $e->id);
            $sch = $scheduled->get($e->id);

            return [
                'employee' => $e,
                'schedule' => $sch?->code,
                'in' => $ev->firstWhere('type', 'in'),
                'out' => $ev->where('type', 'out')->last(),
                'missing' => $sch && in_array($sch->code, $working, true) && $ev->isEmpty(),
            ];
        });

        return view('attendance.index', compact('rows', 'date', 'events'));
    }

    /** Koreksi manual (mis. GPS tidak tersedia) — wajib alasan & tercatat di audit. */
    public function manual(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer'],
            'type' => ['required', 'in:in,out'],
            'occurred_at' => ['required', 'date'],
            'note' => ['required', 'string', 'min:5', 'max:200'],
        ]);
        $employee = Employee::findOrFail($data['employee_id']);
        $event = AttendanceEvent::create([
            'branch_id' => $employee->branch_id ?? $this->branchId(),
            'employee_id' => $employee->id,
            'type' => $data['type'],
            'occurred_at' => $data['occurred_at'],
            'within_geofence' => false,
            'flag' => 'manual',
            'note' => $data['note'],
            'created_by' => $request->user()->id,
        ]);
        Audit::log('attendance.manual', $event, null, ['employee' => $employee->name, 'type' => $data['type'], 'at' => $data['occurred_at']], $data['note']);

        return back()->with('success', 'Koreksi absensi dicatat.');
    }

    public function export(Request $request)
    {
        $from = $request->query('from', today()->startOfMonth()->toDateString());
        $to = $request->query('to', today()->toDateString());
        $rows = AttendanceEvent::forBranch()->with('employee')->whereBetween('occurred_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->orderBy('occurred_at')->get()->map(fn ($e) => [
                $e->employee?->code, $e->employee?->name, $e->occurred_at->format('Y-m-d'), $e->occurred_at->format('H:i'),
                $e->type === 'in' ? 'Masuk' : 'Pulang', $e->distance_m, $e->within_geofence ? 'Ya' : 'Tidak', $e->flag, $e->note,
            ]);

        return $this->csv("absensi-{$from}-{$to}.csv", ['Kode', 'Nama', 'Tanggal', 'Jam', 'Jenis', 'Jarak (m)', 'Dalam radius', 'Tanda', 'Catatan'], $rows);
    }
}
