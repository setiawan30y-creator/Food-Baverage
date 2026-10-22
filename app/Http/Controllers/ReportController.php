<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Models\WasteRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Laporan penjualan, menu & margin, pembayaran, stok & waste. @2026-Alifiandra */
class ReportController extends Controller
{
    protected function range(Request $request): array
    {
        $from = Carbon::parse($request->query('from', today()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', today()->toDateString()))->endOfDay();

        return [$from, $to];
    }

    protected function branchScope(Request $request): ?int
    {
        // Pemilik/manajer tanpa batasan cabang dapat melihat konsolidasi semua cabang
        return $request->query('scope') === 'all' && $request->user()->branch_id === null ? null : $this->branchId();
    }

    public function sales(Request $request)
    {
        [$from, $to] = $this->range($request);
        $branchId = $this->branchScope($request);
        $base = Order::query()->where('payment_status', 'paid')->whereBetween('paid_at', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $daily = (clone $base)->selectRaw('DATE(paid_at) as d, COUNT(*) as orders, SUM(subtotal) as subtotal, SUM(discount) as discount, SUM(tax) as tax, SUM(service) as service, SUM(total) as total, SUM(cost_total) as cost')
            ->groupBy('d')->orderBy('d')->get();
        $byChannel = (clone $base)->selectRaw('channel, COUNT(*) as orders, SUM(total) as total')->groupBy('channel')->get();
        $byBranch = (clone $base)->with('branch')->selectRaw('branch_id, COUNT(*) as orders, SUM(total) as total')->groupBy('branch_id')->get();
        $byCashier = (clone $base)->with('cashier')->selectRaw('cashier_id, COUNT(*) as orders, SUM(total) as total')->groupBy('cashier_id')->get();
        $payments = Payment::query()->where('status', 'success')->whereBetween('paid_at', [$from, $to])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->selectRaw('method, COUNT(*) as count, SUM(amount) as total, SUM(provider_fee) as fee')->groupBy('method')->get();

        if ($request->query('export')) {
            return $this->csv('penjualan.csv', ['Tanggal', 'Pesanan', 'Subtotal', 'Diskon', 'Pajak', 'Service', 'Total', 'HPP', 'Laba kotor'],
                $daily->map(fn ($r) => [$r->d, $r->orders, $r->subtotal, $r->discount, $r->tax, $r->service, $r->total, $r->cost, $r->subtotal - $r->discount - $r->cost]));
        }

        return view('reports.sales', compact('daily', 'byChannel', 'byBranch', 'byCashier', 'payments', 'from', 'to'));
    }

    public function menu(Request $request)
    {
        [$from, $to] = $this->range($request);
        $branchId = $this->branchScope($request);
        $rows = OrderItem::query()->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', tenancy()->tenantId())
            ->when($branchId, fn ($q) => $q->where('orders.branch_id', $branchId))
            ->where('orders.payment_status', 'paid')->whereBetween('orders.paid_at', [$from, $to])
            ->selectRaw('order_items.name, SUM(order_items.qty) as qty, SUM(order_items.subtotal) as revenue, SUM(order_items.unit_cost * order_items.qty) as cost')
            ->groupBy('order_items.name')->orderByDesc('qty')->get()
            ->map(function ($r) {
                $r->margin = $r->revenue > 0 ? ($r->revenue - $r->cost) / $r->revenue * 100 : 0;

                return $r;
            });

        if ($request->query('export')) {
            return $this->csv('menu-margin.csv', ['Menu', 'Terjual', 'Pendapatan', 'HPP', 'Margin %'],
                $rows->map(fn ($r) => [$r->name, $r->qty, $r->revenue, round($r->cost, 2), round($r->margin, 1)]));
        }

        return view('reports.menu', compact('rows', 'from', 'to'));
    }

    /** Pemakaian bahan teoritis (penjualan & produksi) vs selisih opname & waste. */
    public function stock(Request $request)
    {
        [$from, $to] = $this->range($request);
        $rows = StockMovement::forBranch()->with('item')->whereBetween('created_at', [$from, $to])
            ->selectRaw('inventory_item_id, type, SUM(qty) as qty, SUM(total_cost) as value')
            ->groupBy('inventory_item_id', 'type')->get()
            ->groupBy('inventory_item_id')
            ->map(function ($g) {
                $by = $g->keyBy('type');
                $val = fn ($t) => (float) ($by[$t]->qty ?? 0);

                return [
                    'item' => $g->first()->item,
                    'in' => $val('purchase') + $val('production_in') + $val('opening'),
                    'sale' => -$val('sale') - $val('sale_return'),
                    'production' => -$val('production_out'),
                    'waste' => -$val('waste'),
                    'opname' => $val('opname') + $val('adjustment'),
                    'loss_value' => -((float) ($by['waste']->value ?? 0)) - min(0, (float) ($by['opname']->value ?? 0)),
                ];
            })->sortByDesc('loss_value')->values();

        return view('reports.stock', compact('rows', 'from', 'to'));
    }

    public function waste(Request $request)
    {
        [$from, $to] = $this->range($request);
        $records = WasteRecord::forBranch()->with(['item', 'requester'])->where('status', 'approved')->whereBetween('created_at', [$from, $to])->get();
        $byItem = $records->groupBy('inventory_item_id')->map(fn ($g) => ['item' => $g->first()->item, 'qty' => $g->sum('qty'), 'total' => $g->sum('total_cost')])->sortByDesc('total');
        $byReason = $records->groupBy('reason')->map->sum('total_cost');
        $byUser = $records->groupBy('requested_by')->map(fn ($g) => ['user' => $g->first()->requester, 'count' => $g->count(), 'total' => $g->sum('total_cost')]);

        return view('reports.waste', compact('byItem', 'byReason', 'byUser', 'from', 'to'));
    }
}
