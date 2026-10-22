<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\MenuStockService;
use App\Domain\Ordering\OrderService;
use App\Domain\Shared\Audit;
use App\Models\BranchMenu;
use App\Models\CashierShift;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Kasir / POS: dine-in, takeaway, pembayaran. @2026-Alifiandra */
class PosController extends Controller
{
    public function index(MenuStockService $menuStock)
    {
        $settings = BranchMenu::where('branch_id', $this->branchId())->get()->keyBy('menu_id');
        $menus = Menu::with(['modifiers' => fn ($q) => $q->where('is_active', true)])
            ->where('is_active', true)->orderBy('name')->get()
            ->filter(fn ($m) => ! ($s = $settings->get($m->id)) || $s->is_available);
        $stock = $menuStock->forBranch($this->branch(), $menus);
        $block = $this->branch()->block_out_of_stock;
        $menus = $menus
            ->map(fn ($m) => [
                'id' => $m->id,
                'name' => $m->name,
                'category' => $m->menu_category_id,
                'price' => (float) ($settings->get($m->id)?->price ?? $m->price),
                'stock' => $stock[$m->id]['stock'],
                'sold_out' => (bool) ($settings->get($m->id)?->sold_out) || ! $m->isScheduledAt(now()) || ($block && $stock[$m->id]['stock'] !== null && $stock[$m->id]['stock'] <= 0),
                'photo' => $m->photoUrl(),
                'modifiers' => $m->modifiers->map(fn ($x) => ['id' => $x->id, 'group' => $x->group_name, 'name' => $x->name, 'price' => (float) $x->price])->values(),
            ])->values();

        return view('pos.index', [
            'menus' => $menus,
            'categories' => MenuCategory::where('is_active', true)->orderBy('sort')->orderBy('name')->get(['id', 'name']),
            'tables' => DiningTable::forBranch()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'status']),
            'shift' => $this->currentShift(),
            'openOrders' => Order::forBranch()->where('payment_status', '!=', 'paid')->whereNotIn('status', ['cancelled'])
                ->with('table')->latest()->limit(30)->get(),
            'branch' => $this->branch(),
        ]);
    }

    /** Sisa porsi terbaru untuk layar kasir (dipanggil berkala & setelah transaksi). */
    public function stock(MenuStockService $menuStock)
    {
        $branch = $this->branch();
        $settings = BranchMenu::where('branch_id', $branch->id)->get()->keyBy('menu_id');
        $menus = Menu::where('is_active', true)->get();
        $stock = $menuStock->forBranch($branch, $menus);

        return response()->json($menus->mapWithKeys(fn ($m) => [$m->id => [
            'stock' => $stock[$m->id]['stock'],
            'sold_out' => (bool) ($settings->get($m->id)?->sold_out) || ! $m->isScheduledAt(now())
                || ($branch->block_out_of_stock && $stock[$m->id]['stock'] !== null && $stock[$m->id]['stock'] <= 0),
        ]]));
    }

    public function store(Request $request, OrderService $orders)
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['dine_in', 'takeaway'])],
            'dining_table_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'note' => ['nullable', 'string', 'max:255'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'discount_reason' => ['nullable', 'string', 'max:150'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.modifier_ids' => ['nullable', 'array'],
            'items.*.note' => ['nullable', 'string', 'max:150'],
            'idempotency_key' => ['required', 'string', 'max:80'],
        ]);

        if (($data['discount'] ?? 0) > 0) {
            if (! trim((string) ($data['discount_reason'] ?? ''))) {
                return response()->json(['message' => 'Alasan diskon wajib diisi.'], 422);
            }
            $subtotal = collect($orders->buildLines($this->branch(), $data['items']))->sum('subtotal');
            $pct = $subtotal > 0 ? $data['discount'] / $subtotal * 100 : 0;
            if ($pct > config('fnb.approval.discount_percent') && ! $request->user()->hasAbility('orders.void')) {
                return response()->json(['message' => 'Diskon di atas '.config('fnb.approval.discount_percent').'% memerlukan persetujuan manajer.'], 422);
            }
        }

        $order = $orders->create($this->branch(), $data, 'pos');

        return response()->json([
            'id' => $order->id,
            'number' => $order->number,
            'total' => (float) $order->total,
            'subtotal' => (float) $order->subtotal,
            'tax' => (float) $order->tax,
            'service' => (float) $order->service,
            'discount' => (float) $order->discount,
            'pay_url' => route('orders.pay', $order),
            'receipt_url' => route('orders.receipt', $order),
        ]);
    }

    public function pay(Request $request, Order $order, OrderService $orders)
    {
        $this->ensureBranch($order);
        $shift = $this->currentShift();
        if (! $shift) {
            $msg = 'Buka shift kasir terlebih dahulu sebelum menerima pembayaran.';

            return $request->expectsJson() ? response()->json(['message' => $msg], 422) : back()->with('error', $msg);
        }
        $data = $request->validate([
            'method' => ['required', Rule::in(array_keys(config('fnb.payment_methods')))],
            'received' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:80'],
        ]);
        if ($data['method'] !== 'cash' && ! trim((string) ($data['reference'] ?? ''))) {
            $msg = 'Isi nomor referensi / bukti pembayaran non-tunai yang sudah diverifikasi.';

            return $request->expectsJson() ? response()->json(['message' => $msg], 422) : back()->with('error', $msg);
        }

        $payment = $orders->pay($order, $data['method'], (float) ($data['received'] ?? 0), $data['reference'] ?? null, $shift, $data['idempotency_key'] ?? (string) Str::uuid());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'change' => (float) $payment->change, 'receipt_url' => route('orders.receipt', $order)]);
        }

        return redirect()->route('orders.show', $order)->with('success', 'Pembayaran diterima. Kembalian '.rupiah($payment->change));
    }

    protected function currentShift(): ?CashierShift
    {
        return CashierShift::forBranch()->where('user_id', auth()->id())->where('status', 'open')->latest()->first();
    }
}
