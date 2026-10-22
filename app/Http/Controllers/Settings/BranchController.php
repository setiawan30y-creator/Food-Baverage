<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Shared\Audit;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\KitchenStation;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Cabang, lokasi (geofence), jam buka, pajak/service, Order Ahead, stasiun dapur. @2026-Alifiandra */
class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::with('stations')->orderBy('id')->get();

        return view('settings.branches', compact('branches'));
    }

    public function store(Request $request)
    {
        $tenant = tenancy()->tenant();
        if (Branch::count() >= $tenant->max_branches) {
            return back()->with('error', "Paket Anda dibatasi {$tenant->max_branches} cabang.");
        }
        $branch = Branch::create($this->validated($request) + ['tenant_id' => $tenant->id]);
        KitchenStation::create(['branch_id' => $branch->id, 'name' => 'Dapur']);
        KitchenStation::create(['branch_id' => $branch->id, 'name' => 'Minuman']);
        Audit::log('branch.created', $branch);

        return back()->with('success', 'Cabang ditambahkan.');
    }

    public function update(Request $request, Branch $branch)
    {
        $before = $branch->toArray();
        $branch->update($this->validated($request, $branch));
        Audit::log('branch.updated', $branch, $before, $branch->fresh()->toArray());

        return back()->with('success', 'Cabang disimpan.');
    }

    public function storeStation(Request $request, Branch $branch)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60']]);
        $branch->stations()->create($data + ['tenant_id' => $branch->tenant_id]);

        return back()->with('success', 'Stasiun ditambahkan.');
    }

    public function destroyStation(KitchenStation $station)
    {
        $station->delete();

        return back()->with('success', 'Stasiun dihapus.');
    }

    protected function validated(Request $request, ?Branch $branch = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['required', 'alpha_num', 'max:10', Rule::unique('branches')->where('tenant_id', tenancy()->tenantId())->ignore($branch?->id)],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius' => ['required', 'integer', 'min:10', 'max:5000'],
            'open_time' => ['required', 'date_format:H:i'],
            'close_time' => ['required', 'date_format:H:i', 'after:open_time'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'service_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'pickup_slot_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'pickup_capacity_per_slot' => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        $data['code'] = strtoupper($data['code']);
        $data['order_ahead_enabled'] = $request->boolean('order_ahead_enabled');
        $data['weather_widget'] = $request->boolean('weather_widget');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
