<?php

namespace App\Http\Controllers\Public;

use App\Domain\Ordering\OrderService;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Order Ahead (pesan dari rumah, ambil di outlet). Guest checkout. @2026-Alifiandra */
class OrderAheadController extends Controller
{
    use PublicMenu;

    public function index(string $slug)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->bootTenant($tenant);
        $branches = Branch::where('is_active', true)->where('order_ahead_enabled', true)->orderBy('name')->get();

        return view('public.branches', compact('tenant', 'branches'));
    }

    public function menu(string $slug, int $branchId, OrderService $orders)
    {
        [$tenant, $branch] = $this->resolve($slug, $branchId);

        return view('public.menu', $this->menuPayload($branch) + [
            'mode' => 'order_ahead',
            'table' => null,
            'branch' => $branch,
            'slots' => $orders->pickupSlots($branch),
            'submitUrl' => route('oa.store', [$slug, $branch->id]),
            'isOpen' => $branch->isOpenAt(now()),
        ]);
    }

    public function store(Request $request, string $slug, int $branchId, OrderService $orders)
    {
        [$tenant, $branch] = $this->resolve($slug, $branchId);
        $data = $request->validate($this->itemRules() + [
            'customer_name' => ['required', 'string', 'max:60'],
            'customer_phone' => ['required', 'string', 'min:8', 'max:20'],
            'pickup' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:200'],
            'marketing_consent' => ['nullable', 'boolean'],
        ]);

        // Validasi slot di server (jam buka & kapasitas)
        if ($data['pickup'] === 'asap') {
            if (! $branch->isOpenAt(now())) {
                return response()->json(['message' => 'Outlet sedang tutup. Pilih slot waktu pengambilan.'], 422);
            }
            $pickupAt = now()->addMinutes(20);
        } else {
            $slot = collect($orders->pickupSlots($branch, 200))->firstWhere('value', $data['pickup']);
            if (! $slot || $slot['full']) {
                return response()->json(['message' => 'Slot pengambilan tidak tersedia / sudah penuh. Silakan pilih slot lain.'], 422);
            }
            $pickupAt = Carbon::parse($slot['value']);
        }

        $order = $orders->create($branch, $data + ['type' => 'pickup', 'pickup_at' => $pickupAt], 'order_ahead');

        return response()->json(['ok' => true, 'number' => $order->number, 'track_url' => route('track.show', $order->tracking_token)]);
    }

    protected function resolve(string $slug, int $branchId): array
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $branch = Branch::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('order_ahead_enabled', true)->findOrFail($branchId);
        $this->bootTenant($tenant, $branch);

        return [$tenant, $branch];
    }
}
