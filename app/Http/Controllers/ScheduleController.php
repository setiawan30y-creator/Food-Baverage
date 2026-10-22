<?php

namespace App\Http\Controllers;

use App\Domain\Shared\Audit;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ShiftTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Jadwal shift mingguan & papan shift siap cetak A4 landscape. @2026-Alifiandra */
class ScheduleController extends Controller
{
    protected function weekData(Request $request): array
    {
        $start = Carbon::parse($request->query('week', today()->toDateString()))->startOfWeek(Carbon::MONDAY);
        $days = collect(range(0, 6))->map(fn ($i) => $start->copy()->addDays($i));
        $employees = Employee::where('status', 'active')->where('branch_id', $this->branchId())
            ->when($request->position, fn ($q, $p) => $q->where('position', $p))
            ->orderBy('position')->orderBy('name')->get();
        $schedules = EmployeeSchedule::forBranch()->whereBetween('date', [$days->first()->toDateString(), $days->last()->toDateString()])->get()
            ->groupBy('employee_id')->map(fn ($g) => $g->keyBy(fn ($s) => $s->date->toDateString()));
        $templates = ShiftTemplate::orderBy('id')->get();

        return compact('start', 'days', 'employees', 'schedules', 'templates');
    }

    public function index(Request $request)
    {
        $data = $this->weekData($request);
        // Rekap staf per hari per kode untuk cek kebutuhan staf
        $coverage = [];
        foreach ($data['days'] as $d) {
            $key = $d->toDateString();
            foreach ($data['schedules'] as $empSchedules) {
                if ($s = $empSchedules->get($key)) {
                    $coverage[$key][$s->code] = ($coverage[$key][$s->code] ?? 0) + 1;
                }
            }
        }

        return view('schedule.index', $data + ['coverage' => $coverage]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'cells' => ['array'],
            'cells.*' => ['array'],
            'cells.*.*' => ['nullable', 'string', 'max:10'],
        ]);
        $codes = ShiftTemplate::pluck('code')->all();
        $empIds = Employee::where('branch_id', $this->branchId())->pluck('id')->all();
        $changed = 0;

        DB::transaction(function () use ($data, $codes, $empIds, &$changed) {
            foreach ($data['cells'] ?? [] as $empId => $days) {
                if (! in_array((int) $empId, $empIds, true)) {
                    continue;
                }
                foreach ($days as $date => $code) {
                    $date = Carbon::parse($date)->toDateString();
                    $existing = EmployeeSchedule::where('employee_id', $empId)->whereDate('date', $date)->first();
                    if (! $code) {
                        if ($existing) {
                            $existing->delete();
                            $changed++;
                        }

                        continue;
                    }
                    if (! in_array($code, $codes, true)) {
                        continue;
                    }
                    if ($existing && $existing->code === $code) {
                        continue;
                    }
                    EmployeeSchedule::updateOrCreate(
                        ['employee_id' => $empId, 'date' => $date],
                        ['branch_id' => $this->branchId(), 'code' => $code]
                    );
                    $changed++;
                }
            }
        });
        if ($changed) {
            Audit::log('schedule.updated', null, null, ['cells_changed' => $changed]);
        }

        return back()->with('success', "Jadwal disimpan ({$changed} perubahan).");
    }

    public function print(Request $request)
    {
        return view('schedule.print', $this->weekData($request));
    }

    public function saveTemplate(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:40'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
        ShiftTemplate::updateOrCreate(['code' => strtoupper($data['code'])], [
            'name' => $data['name'], 'start_time' => $data['start_time'] ?? null, 'end_time' => $data['end_time'] ?? null,
            'color' => $data['color'], 'is_working' => $request->boolean('is_working'),
        ]);

        return back()->with('success', 'Master shift disimpan.');
    }
}
