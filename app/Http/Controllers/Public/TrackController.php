<?php

namespace App\Http\Controllers\Public;

use App\Domain\Payments\GatewayService;
use App\Http\Controllers\Controller;
use App\Models\GatewayCharge;
use Illuminate\Http\Request;
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

    public function show(string $token, GatewayService $gateway)
    {
        $order = $this->find($token);

        return view('public.track', ['order' => $order, 'payChannels' => $this->payChannels($order, $gateway)]);
    }

    /** Kanal bayar online untuk pelanggan (hanya bila pemilik mengizinkan & pesanan belum lunas). */
    protected function payChannels(Order $order, GatewayService $gateway): array
    {
        $i = $gateway->active();
        if (! $i || ! $i->setting('public_pay', true) || $order->payment_status === 'paid' || $order->status === 'cancelled') {
            return [];
        }

        return $gateway->channelOptions($i);
    }

    public function pay(Request $request, string $token, GatewayService $gateway)
    {
        $order = $this->find($token);
        abort_if(empty($this->payChannels($order, $gateway)), 422, 'Pembayaran online tidak tersedia untuk pesanan ini.');
        $data = $request->validate(['channel' => ['required', 'string', 'max:30']]);
        $charge = $gateway->createCharge($order, $data['channel']);

        return response()->json(['charge' => $charge->toPublic() + ['qr_image' => $charge->payload['_qr_url'] ?? null]]);
    }

    public function payStatus(string $token, string $reference, GatewayService $gateway)
    {
        $order = $this->find($token);
        $charge = GatewayCharge::where('order_id', $order->id)->where('reference', $reference)->firstOrFail();
        $charge = $gateway->refresh($charge);

        return response()->json(['status' => $charge->status, 'order_paid' => $order->fresh()->payment_status === 'paid']);
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
