<?php

namespace App\Http\Controllers;

use App\Domain\Ordering\OrderService;
use App\Models\KitchenStation;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

/** Kitchen Display System per stasiun. Status "siap" dikonfirmasi staf. @2026-Alifiandra */
class KitchenController extends Controller
{
    public function index(Request $request)
    {
        $stations = KitchenStation::forBranch()->where('is_active', true)->pluck('name');

        return view('kitchen.index', ['stations' => $stations, 'station' => $request->query('station')]);
    }

    public function feed(Request $request)
    {
        $station = $request->query('station');
        $orders = Order::forBranch()->whereIn('status', ['accepted', 'preparing', 'ready'])
            ->with(['items' => fn ($q) => $q->whereNotIn('kitchen_status', ['cancelled', 'served'])->with('menu:id,prep_minutes'), 'table:id,name'])
            ->orderByRaw('CASE WHEN pickup_at IS NULL THEN created_at ELSE pickup_at END')
            ->get()
            ->map(function ($o) use ($station) {
                $items = $o->items->filter(fn ($i) => ! $station || $i->station_name === $station)->values();
                if ($items->isEmpty()) {
                    return null;
                }
                $prep = (int) $items->max(fn ($i) => $i->menu?->prep_minutes ?? 10);
                $elapsed = (int) $o->created_at->diffInMinutes(now());

                return [
                    'id' => $o->id,
                    'number' => $o->number,
                    'status' => $o->status,
                    'channel' => $o->channelLabel(),
                    'type' => $o->typeLabel(),
                    'table' => $o->table?->name,
                    'customer' => $o->customer_name,
                    'note' => $o->note,
                    'pickup_at' => $o->pickup_at?->format('H:i'),
                    'paid' => $o->payment_status === 'paid',
                    'elapsed' => $elapsed,
                    'late' => $o->status !== 'ready' && $elapsed > $prep,
                    'prep' => $prep,
                    'items' => $items->map(fn ($i) => [
                        'id' => $i->id, 'name' => $i->name, 'qty' => $i->qty, 'note' => $i->note,
                        'mods' => collect($i->modifiers ?? [])->pluck('name')->implode(', '),
                        'status' => $i->kitchen_status, 'station' => $i->station_name,
                    ]),
                ];
            })->filter()->values();

        return response()->json(['orders' => $orders, 'server_time' => now()->format('H:i:s')]);
    }

    public function item(Request $request, OrderItem $item, OrderService $orders)
    {
        $order = $item->order;
        abort_unless($order && $order->tenant_id === tenancy()->tenantId(), 404);
        $this->ensureBranch($order);
        $data = $request->validate(['status' => ['required', 'in:preparing,ready']]);
        $item->update(['kitchen_status' => $data['status'], 'ready_at' => $data['status'] === 'ready' ? now() : null]);
        if ($order->status === 'accepted') {
            $orders->changeStatus($order, 'preparing');
        }

        return response()->json(['ok' => true]);
    }

    public function ready(Order $order, OrderService $orders)
    {
        $this->ensureBranch($order);
        if (in_array($order->status, ['accepted', 'preparing'], true)) {
            $orders->changeStatus($order, 'ready', 'Dikonfirmasi siap oleh dapur');
        }

        return response()->json(['ok' => true]);
    }

    public function served(Order $order, OrderService $orders)
    {
        $this->ensureBranch($order);
        if ($order->status === 'ready' && $order->payment_status === 'paid' && $order->type !== 'pickup') {
            $orders->changeStatus($order, 'completed', 'Disajikan');
        } else {
            $order->items()->where('kitchen_status', 'ready')->update(['kitchen_status' => 'served']);
        }

        return response()->json(['ok' => true]);
    }
}
