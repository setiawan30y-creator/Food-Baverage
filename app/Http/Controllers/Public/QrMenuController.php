<?php

namespace App\Http\Controllers\Public;

use App\Domain\Ordering\OrderService;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\DiningTable;
use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * QR meja: QR hanya mengidentifikasi meja (tenant/cabang/meja), BUKAN izin
 * melihat pesanan orang lain. Status pesanan dilihat lewat token pelacakan unik.
 * @2026-Alifiandra
 */
class QrMenuController extends Controller
{
    use PublicMenu;

    protected function resolve(string $token): DiningTable
    {
        $table = DiningTable::withoutGlobalScopes()->where('qr_token', $token)->where('is_active', true)->firstOrFail();
        $this->bootTenant(Tenant::findOrFail($table->tenant_id), Branch::withoutGlobalScopes()->findOrFail($table->branch_id));

        return $table;
    }

    public function show(string $token)
    {
        $table = $this->resolve($token);
        $branch = tenancy()->branch();

        return view('public.menu', $this->menuPayload($branch) + [
            'mode' => 'qr',
            'table' => $table,
            'branch' => $branch,
            'submitUrl' => route('qr.order', $token),
            'isOpen' => $branch->isOpenAt(now()),
        ]);
    }

    public function store(Request $request, string $token, OrderService $orders)
    {
        $table = $this->resolve($token);
        $branch = tenancy()->branch();
        if (! $branch->isOpenAt(now())) {
            return response()->json(['message' => 'Maaf, outlet sedang tutup.'], 422);
        }
        $data = $request->validate($this->itemRules() + [
            'customer_name' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:200'],
        ]);
        $order = $orders->create($branch, $data + ['type' => 'dine_in', 'dining_table_id' => $table->id], 'qr');

        return response()->json(['ok' => true, 'number' => $order->number, 'track_url' => route('track.show', $order->tracking_token)]);
    }
}
