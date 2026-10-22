<?php

namespace App\Http\Controllers;

use App\Domain\Ordering\OrderService;
use App\Domain\Shared\Audit;
use App\Models\Order;
use Illuminate\Http\Request;

/** Daftar pesanan, detail, status, batal/refund, nota, verifikasi pickup. @2026-Alifiandra */
class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = Order::forBranch()->with(['table', 'cashier'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->channel, fn ($q, $c) => $q->where('channel', $c))
            ->when($request->payment, fn ($q, $p) => $q->where('payment_status', $p))
            ->when($request->date, fn ($q, $d) => $q->whereDate('created_at', $d))
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('number', 'like', "%{$s}%")
                ->orWhere('customer_name', 'like', "%{$s}%")->orWhere('pickup_code', $s)))
            ->latest()->paginate(30)->withQueryString();

        return view('orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $this->ensureBranch($order);
        $order->load(['items', 'payments.user', 'payments.account', 'histories.user', 'table', 'cashier']);

        return view('orders.show', compact('order'));
    }

    public function status(Request $request, Order $order, OrderService $orders)
    {
        $this->ensureBranch($order);
        $data = $request->validate(['status' => ['required', 'in:accepted,preparing,ready,completed'], 'note' => ['nullable', 'string', 'max:150']]);
        $orders->changeStatus($order, $data['status'], $data['note'] ?? null);

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Status pesanan diperbarui.');
    }

    public function cancel(Request $request, Order $order, OrderService $orders)
    {
        $this->ensureBranch($order);
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:200']]);
        $sensitive = $order->payment_status === 'paid' || in_array($order->status, ['preparing', 'ready'], true);
        abort_if($sensitive && ! $request->user()->hasAbility('orders.void'), 403, 'Pembatalan setelah bayar / setelah diproses dapur memerlukan manajer.');
        $orders->cancel($order, $data['reason']);

        return back()->with('success', 'Pesanan dibatalkan.');
    }

    public function receipt(Order $order)
    {
        $this->ensureBranch($order);
        $order->load(['items', 'payments', 'cashier', 'table', 'branch']);
        $reprint = $order->print_count > 0;
        $order->increment('print_count');
        if ($reprint) {
            Audit::log('order.receipt_reprint', $order, null, ['count' => $order->print_count]);
        }

        return view('orders.receipt', ['order' => $order, 'reprint' => $reprint, 'width' => request('w', '58')]);
    }

    /** Verifikasi kode pengambilan Order Ahead oleh staf. */
    public function verifyPickup(Request $request, OrderService $orders)
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $order = Order::forBranch()->where('pickup_code', $data['code'])->whereNotIn('status', ['completed', 'cancelled'])->first();
        if (! $order) {
            return back()->with('error', 'Kode pengambilan tidak ditemukan atau sudah dipakai.');
        }
        if ($order->status !== 'ready') {
            return redirect()->route('orders.show', $order)->with('error', 'Pesanan belum dikonfirmasi siap oleh dapur.');
        }
        if ($order->payment_status !== 'paid') {
            return redirect()->route('orders.show', $order)->with('error', 'Kode cocok, tetapi pesanan belum lunas. Terima pembayaran dahulu.');
        }
        $orders->changeStatus($order, 'completed', 'Diambil pelanggan (kode terverifikasi)');

        return redirect()->route('orders.show', $order)->with('success', 'Kode cocok. Pesanan diserahkan.');
    }
}
