<?php

namespace App\Http\Controllers;

use App\Domain\Payments\GatewayService;
use App\Models\GatewayCharge;
use App\Models\Order;
use App\Models\TenantIntegration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * QRIS dinamis / Virtual Account dari layar kasir, dan webhook dari gateway.
 * @2026-Alifiandra
 */
class PaymentGatewayController extends Controller
{
    public function __construct(protected GatewayService $gateway) {}

    public function charge(Request $request, Order $order)
    {
        $this->ensureBranch($order);
        $data = $request->validate(['channel' => ['required', 'string', 'max:30']]);
        $charge = $this->gateway->createCharge($order, $data['channel']);

        return response()->json(['charge' => $charge->toPublic() + ['qr_image' => $charge->payload['_qr_url'] ?? null]]);
    }

    public function status(GatewayCharge $charge)
    {
        $this->ensureBranch($charge);
        $charge = $this->gateway->refresh($charge);
        $order = Order::find($charge->order_id);

        return response()->json([
            'status' => $charge->status, 'order_paid' => $order?->payment_status === 'paid',
            'receipt_url' => $order ? route('orders.receipt', $order) : null,
        ]);
    }

    /** Webhook (tanpa login & tanpa CSRF). Token di URL menentukan usaha; isi diverifikasi tanda tangan/token gateway. */
    public function webhook(Request $request, string $token)
    {
        $integration = TenantIntegration::withoutGlobalScopes()->where('webhook_token', $token)->where('type', 'payment')->first();
        if (! $integration) {
            return response()->json(['message' => 'not found'], 404);
        }
        try {
            $result = $this->gateway->handleWebhook($integration, $request);
        } catch (\RuntimeException $e) {
            Log::warning('Webhook gateway ditolak', ['tenant' => $integration->tenant_id, 'error' => $e->getMessage()]);

            return response()->json(['message' => $e->getMessage()], 401);
        }

        return response()->json(['message' => $result]);
    }
}
