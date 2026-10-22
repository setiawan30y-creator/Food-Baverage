<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryService;
use App\Models\CashClosing;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\WasteRecord;
use Illuminate\Http\Request;

/** @2026-Alifiandra */
class DashboardController extends Controller
{
    public function index(InventoryService $inventory)
    {
        $branchId = $this->branchId();
        $today = Order::forBranch()->whereDate('created_at', today());
        $paidToday = (clone $today)->where('payment_status', 'paid');

        $stats = [
            'sales' => (float) (clone $paidToday)->sum('total'),
            'orders' => (clone $paidToday)->count(),
            'cost' => (float) (clone $paidToday)->sum('cost_total'),
            'active' => Order::forBranch()->whereIn('status', ['new', 'accepted', 'preparing', 'ready'])->count(),
            'new_online' => Order::forBranch()->where('status', 'new')->count(),
        ];
        $stats['avg'] = $stats['orders'] ? $stats['sales'] / $stats['orders'] : 0;

        $trend = collect(range(6, 0))->map(function ($d) {
            $date = today()->subDays($d);

            return [
                'label' => $date->translatedFormat('D d/m'),
                'total' => (float) Order::forBranch()->where('payment_status', 'paid')->whereDate('paid_at', $date)->sum('total'),
            ];
        });

        $top = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', tenancy()->tenantId())
            ->where('orders.branch_id', $branchId)
            ->where('orders.payment_status', 'paid')
            ->whereDate('orders.paid_at', '>=', today()->subDays(6))
            ->selectRaw('order_items.name, SUM(order_items.qty) as qty, SUM(order_items.subtotal) as total')
            ->groupBy('order_items.name')->orderByDesc('qty')->limit(5)->get();

        $balances = $inventory->balances($branchId);
        $lowStock = InventoryItem::where('is_active', true)->where('min_stock', '>', 0)->get()
            ->filter(fn ($i) => ($balances[$i->id] ?? 0) <= $i->min_stock)
            ->map(fn ($i) => ['item' => $i, 'balance' => $balances[$i->id] ?? 0])->take(8);

        $pendingWaste = WasteRecord::forBranch()->where('status', 'pending')->count();
        $pendingClosing = CashClosing::forBranch()->where('approval_status', 'pending')->count();

        return view('dashboard', compact('stats', 'trend', 'top', 'lowStock', 'pendingWaste', 'pendingClosing'));
    }

    public function switchBranch(Request $request)
    {
        $data = $request->validate(['branch_id' => ['required', 'integer']]);
        $branch = \App\Models\Branch::where('is_active', true)->findOrFail($data['branch_id']);
        abort_unless($request->user()->canAccessBranch($branch->id), 403);
        $request->session()->put('branch_id', $branch->id);

        return back()->with('success', 'Cabang aktif: '.$branch->name);
    }
}
