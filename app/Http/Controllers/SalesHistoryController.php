<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Riwayat penjualan (pesanan lunas) dengan filter tanggal, metode & rekening, ringkasan dan ekspor. @2026-Alifiandra */
class SalesHistoryController extends Controller
{
    public function index(Request $request)
    {
        $from = Carbon::parse($request->query('from', today()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', today()->toDateString()))->endOfDay();
        $method = $request->query('method');
        $accountId = $request->query('account');

        $payments = Payment::query()->where('payments.status', 'success')->where('payments.branch_id', $this->branchId())
            ->whereBetween('payments.paid_at', [$from, $to])
            ->when($method, fn ($q) => $q->where('payments.method', $method))
            ->when($accountId === 'none', fn ($q) => $q->whereNull('payments.payment_account_id'))
            ->when($accountId && $accountId !== 'none', fn ($q) => $q->where('payments.payment_account_id', (int) $accountId))
            ->when($request->q, fn ($q, $s) => $q->whereHas('order', fn ($o) => $o->where('number', 'like', "%{$s}%")->orWhere('customer_name', 'like', "%{$s}%")));

        $summary = (clone $payments)->selectRaw('method, COUNT(*) as cnt, SUM(amount) as total')->groupBy('method')->get()->keyBy('method');
        $byAccount = (clone $payments)->with('account')->selectRaw('payment_account_id, method, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('payment_account_id', 'method')->get();

        $list = (clone $payments)->with(['order.cashier', 'order.table', 'account', 'user'])->orderByDesc('paid_at');

        if ($request->query('export')) {
            return $this->csv('riwayat-penjualan.csv', ['Waktu bayar', 'No. pesanan', 'Kanal', 'Pelanggan', 'Kasir', 'Metode', 'Rekening', 'Referensi', 'Total'],
                $list->get()->map(fn ($p) => [$p->paid_at?->format('Y-m-d H:i'), $p->order?->number, $p->order?->channelLabel(), $p->order?->customer_name,
                    $p->user?->name, config('fnb.payment_methods.'.$p->method, $p->method), $p->account?->name, $p->reference, $p->amount]));
        }

        return view('sales.history', [
            'rows' => $list->paginate(50)->withQueryString(),
            'summary' => $summary, 'byAccount' => $byAccount, 'from' => $from, 'to' => $to,
            'accounts' => PaymentAccount::usableAt($this->branchId())->orderBy('name')->get(),
            'grand' => ['cnt' => $summary->sum('cnt'), 'total' => $summary->sum('total')],
        ]);
    }
}
