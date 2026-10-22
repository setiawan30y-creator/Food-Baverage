<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;

/** Pelacakan pesanan & nota digital via token unik. @2026-Alifiandra */
class TrackController extends Controller
{
    protected function find(string $token): Order
    {
        $order = Order::withoutGlobalScopes()->where('tracking_token', $token)->firstOrFail();
        tenancy()->setTenant(Tenant::findOrFail($order->tenant_id));

        return $order->load(['items', 'branch', 'table', 'payments']);
    }

    public function show(string $token)
    {
        return view('public.track', ['order' => $this->find($token)]);
    }

    public function status(string $token)
    {
        $o = $this->find($token);

        return response()->json(['status' => $o->status, 'payment_status' => $o->payment_status, 'updated' => $o->updated_at->format('H:i:s')]);
    }

    public function receipt(string $token)
    {
        $order = $this->find($token);
        abort_unless($order->payment_status === 'paid', 404);
        $order->load('cashier');

        return view('orders.receipt', ['order' => $order, 'reprint' => false, 'width' => '80', 'public' => true]);
    }
}
