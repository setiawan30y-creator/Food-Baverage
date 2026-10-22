<?php

namespace App\Domain\Ordering;

use App\Domain\Catalog\MenuStockService;
use App\Domain\Inventory\InventoryService;
use App\Domain\Production\CostingService;
use App\Domain\Shared\Audit;
use App\Domain\Shared\SequenceService;
use App\Models\Branch;
use App\Models\BranchMenu;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\DiningTable;
use App\Models\Menu;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pembuatan pesanan (POS / QR meja / Order Ahead), status, pembayaran, pembatalan.
 * Harga & ketersediaan SELALU divalidasi ulang di server.
 * @2026-Alifiandra
 */
class OrderService
{
    const TRANSITIONS = [
        'new' => ['accepted', 'cancelled'],
        'accepted' => ['preparing', 'ready', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];

    public function __construct(
        protected SequenceService $sequence,
        protected CostingService $costing,
        protected InventoryService $inventory,
        protected MenuStockService $menuStock,
    ) {}

    /**
     * @param  array  $payload  items[{menu_id, qty, modifier_ids[], note}], type, dining_table_id, customer_name,
     *                          customer_phone, note, discount, discount_reason, pickup_at, idempotency_key, marketing_consent
     */
    public function create(Branch $branch, array $payload, string $channel): Order
    {
        $key = $payload['idempotency_key'] ?? null;
        if ($key && ($existing = Order::where('idempotency_key', $key)->first())) {
            return $existing; // request dikirim ulang → tidak membuat pesanan ganda
        }

        $lines = $this->buildLines($branch, $payload['items'] ?? []);
        $subtotal = array_sum(array_column($lines, 'subtotal'));

        $discount = min(max((float) ($payload['discount'] ?? 0), 0), $subtotal);
        $base = $subtotal - $discount;
        $service = round($base * (float) $branch->service_percent / 100);
        $tax = round(($base + $service) * (float) $branch->tax_percent / 100);
        $total = round($base + $service + $tax);

        return DB::transaction(function () use ($branch, $payload, $channel, $lines, $subtotal, $discount, $service, $tax, $total, $key) {
            $table = null;
            $session = null;
            if (! empty($payload['dining_table_id'])) {
                $table = DiningTable::forBranch($branch->id)->where('is_active', true)->findOrFail($payload['dining_table_id']);
                $session = $this->openSession($table);
            }

            $customer = $this->resolveCustomer($payload);
            $prefix = ['pos' => 'POS', 'qr' => 'QR', 'order_ahead' => 'OA'][$channel] ?? 'ORD';

            $order = Order::create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'number' => $this->sequence->next($branch->tenant_id, $branch->id, 'order', $prefix.$branch->code),
                'channel' => $channel,
                'type' => $payload['type'] ?? ($channel === 'order_ahead' ? 'pickup' : 'dine_in'),
                'dining_table_id' => $table?->id,
                'table_session_id' => $session?->id,
                'customer_id' => $customer?->id,
                'customer_name' => $payload['customer_name'] ?? null,
                'customer_phone' => $payload['customer_phone'] ?? null,
                'status' => $channel === 'pos' ? 'accepted' : 'new',
                'payment_status' => 'unpaid',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'discount_reason' => $payload['discount_reason'] ?? null,
                'tax' => $tax,
                'service' => $service,
                'total' => $total,
                'cost_total' => round(array_sum(array_map(fn ($l) => $l['unit_cost'] * $l['qty'], $lines)), 2),
                'pickup_at' => $payload['pickup_at'] ?? null,
                'pickup_code' => $channel === 'order_ahead' ? $this->pickupCode($branch) : null,
                'tracking_token' => Str::random(40),
                'idempotency_key' => $key,
                'cashier_id' => $channel === 'pos' ? auth()->id() : null,
                'note' => $payload['note'] ?? null,
            ]);

            foreach ($lines as $line) {
                $order->items()->create($line);
            }
            $order->histories()->create(['to_status' => $order->status, 'user_id' => auth()->id(), 'note' => 'Pesanan dibuat ('.$order->channelLabel().')']);

            if ($discount > 0) {
                Audit::log('order.discount', $order, null, ['discount' => $discount], $payload['discount_reason'] ?? null);
            }

            return $order;
        });
    }

    /** Validasi item: menu aktif, tersedia di cabang, tidak habis, sesuai jadwal; harga dari server. */
    public function buildLines(Branch $branch, array $items): array
    {
        if (empty($items)) {
            throw ValidationException::withMessages(['items' => 'Keranjang masih kosong.']);
        }
        $menuIds = collect($items)->pluck('menu_id')->filter()->unique()->all();
        $menus = Menu::with(['modifiers' => fn ($q) => $q->where('is_active', true)])
            ->whereIn('id', $menuIds)->where('is_active', true)->get()->keyBy('id');
        $settings = BranchMenu::where('branch_id', $branch->id)->whereIn('menu_id', $menuIds)->get()->keyBy('menu_id');

        $lines = [];
        foreach ($items as $i => $row) {
            $menu = $menus->get($row['menu_id'] ?? 0);
            $qty = (int) ($row['qty'] ?? 0);
            if (! $menu || $qty < 1 || $qty > 999) {
                throw ValidationException::withMessages(["items.$i" => 'Item pesanan tidak valid.']);
            }
            $setting = $settings->get($menu->id);
            if (($setting && (! $setting->is_available || $setting->sold_out)) || ! $menu->isScheduledAt(now())) {
                throw ValidationException::withMessages(["items.$i" => "{$menu->name} sedang tidak tersedia."]);
            }
            $price = (float) ($setting?->price ?? $menu->price);

            $mods = [];
            $modTotal = 0;
            foreach ((array) ($row['modifier_ids'] ?? []) as $modId) {
                $mod = $menu->modifiers->firstWhere('id', (int) $modId);
                if (! $mod) {
                    throw ValidationException::withMessages(["items.$i" => "Pilihan tambahan untuk {$menu->name} tidak valid."]);
                }
                $mods[] = ['id' => $mod->id, 'group' => $mod->group_name, 'name' => $mod->name, 'price' => (float) $mod->price];
                $modTotal += (float) $mod->price;
            }

            $lines[] = [
                'menu_id' => $menu->id,
                'name' => $menu->name,
                'qty' => $qty,
                'price' => $price,
                'modifiers' => $mods,
                'modifiers_total' => $modTotal,
                'note' => isset($row['note']) ? Str::limit((string) $row['note'], 150, '') : null,
                'subtotal' => ($price + $modTotal) * $qty,
                'unit_cost' => round($this->costing->menuUnitCost($menu), 6),
                'station_name' => $menu->station_name ?: 'Dapur',
            ];
        }

        // Tolak bila bahan / stok harian tidak cukup (bisa dimatikan per cabang)
        $this->menuStock->assertCartAvailable($branch, $lines);

        return $lines;
    }

    public function changeStatus(Order $order, string $to, ?string $note = null): Order
    {
        $from = $order->status;
        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw new \DomainException("Status tidak dapat diubah dari {$from} ke {$to}.");
        }
        if ($to === 'cancelled') {
            return $this->cancel($order, $note ?: 'Dibatalkan');
        }
        if ($to === 'completed' && $order->payment_status !== 'paid') {
            throw new \DomainException('Pesanan belum lunas, tidak dapat diselesaikan.');
        }

        DB::transaction(function () use ($order, $from, $to, $note) {
            $data = ['status' => $to];
            if ($to === 'ready') {
                $order->items()->whereNotIn('kitchen_status', ['cancelled', 'served'])->update(['kitchen_status' => 'ready', 'ready_at' => now()]);
            }
            if ($to === 'completed') {
                $data['completed_at'] = now();
                $order->items()->where('kitchen_status', '!=', 'cancelled')->update(['kitchen_status' => 'served']);
            }
            $order->update($data);
            $order->histories()->create(['from_status' => $from, 'to_status' => $to, 'user_id' => auth()->id(), 'note' => $note]);
        });
        if ($to === 'ready') {
            app(\App\Domain\WhatsApp\WhatsAppService::class)->orderReady($order);
        }

        return $order->fresh();
    }

    /** Pembayaran dikonfirmasi oleh kasir (tunai atau non-tunai yang sudah diverifikasi). */
    public function pay(Order $order, string $method, float $received, ?string $reference, ?CashierShift $shift, ?string $idempotencyKey = null, ?int $accountId = null, bool $strictAccount = true): Payment
    {
        if ($idempotencyKey && ($existing = Payment::where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }

        return DB::transaction(function () use ($order, $method, $received, $reference, $shift, $idempotencyKey, $accountId, $strictAccount) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->payment_status === 'paid') {
                throw new \DomainException('Pesanan sudah lunas.');
            }
            if ($order->status === 'cancelled') {
                throw new \DomainException('Pesanan sudah dibatalkan.');
            }
            $total = (float) $order->total;
            if ($method === 'cash' && $received < $total) {
                throw new \DomainException('Uang diterima kurang dari total tagihan.');
            }
            if ($method !== 'cash') {
                $received = $total;
            }
            // Rekening tujuan (bank/QRIS/e-wallet/kas) agar saldo per akun tercatat
            $accountId = app(\App\Domain\Finance\AccountService::class)->resolveForPayment((int) $order->branch_id, $method, $accountId, $strictAccount);

            $payment = Payment::create([
                'tenant_id' => $order->tenant_id,
                'branch_id' => $order->branch_id,
                'order_id' => $order->id,
                'cashier_shift_id' => $shift?->id,
                'method' => $method,
                'payment_account_id' => $accountId,
                'amount' => $total,
                'received' => $received,
                'change' => max(0, $received - $total),
                'status' => 'success',
                'reference' => $reference,
                'provider' => $method === 'cash' ? null : 'manual',
                'idempotency_key' => $idempotencyKey,
                'user_id' => auth()->id(),
                'paid_at' => now(),
            ]);

            $update = ['payment_status' => 'paid', 'paid_at' => now(), 'cashier_shift_id' => $shift?->id, 'cashier_id' => $order->cashier_id ?? auth()->id()];
            if ($order->status === 'new') {
                $update['status'] = 'accepted';
                $order->histories()->create(['from_status' => 'new', 'to_status' => 'accepted', 'user_id' => auth()->id(), 'note' => 'Diterima saat pembayaran']);
            }
            $order->update($update);

            $this->deductStock($order);

            if ($order->customer_id) {
                Customer::whereKey($order->customer_id)->increment('points', (int) floor($total / 10000));
            }

            app(\App\Domain\WhatsApp\WhatsAppService::class)->orderPaid($order);

            return $payment;
        });
    }

    /** Kurangi stok bahan sesuai resep menu yang terjual (sekali per pesanan). */
    public function deductStock(Order $order): void
    {
        if ($order->stock_deducted) {
            return;
        }
        $order->loadMissing('items.menu.recipe.items.item');
        foreach ($order->items as $line) {
            $recipe = $line->menu?->recipe;
            if (! $recipe) {
                continue;
            }
            $yield = max((float) $recipe->yield_qty, 0.0001);
            foreach ($recipe->items as $ri) {
                if (! $ri->item) {
                    continue;
                }
                $qty = (float) $ri->qty * $line->qty / $yield;
                $this->inventory->post($ri->item, $order->branch_id, 'sale', -$qty, $this->costing->itemUnitCost($ri->item), $order, $order->number.' · '.$line->name);
            }
        }
        $order->update(['stock_deducted' => true]);
    }

    public function cancel(Order $order, string $reason): Order
    {
        if ($order->status === 'completed') {
            throw new \DomainException('Pesanan selesai tidak dapat dibatalkan.');
        }
        if ($order->status === 'cancelled') {
            return $order;
        }

        DB::transaction(function () use ($order, $reason) {
            $before = $order->only(['status', 'payment_status', 'total']);
            $startedCooking = in_array($order->status, ['preparing', 'ready'], true);

            if ($order->payment_status === 'paid') {
                $order->payments()->where('status', 'success')->update(['status' => 'refunded']);
                $order->payment_status = 'refunded';
                // Bahan dikembalikan ke stok hanya bila dapur belum mulai memasak
                if ($order->stock_deducted && ! $startedCooking) {
                    $movements = \App\Models\StockMovement::where('reference_type', 'Order')->where('reference_id', $order->id)->where('type', 'sale')->get();
                    foreach ($movements as $m) {
                        $this->inventory->post($m->item, $order->branch_id, 'sale_return', abs($m->qty), $m->unit_cost, $order, 'Batal '.$order->number);
                    }
                }
            }

            $from = $order->status;
            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->cancel_reason = $reason;
            $order->save();
            $order->items()->update(['kitchen_status' => 'cancelled']);
            $order->histories()->create(['from_status' => $from, 'to_status' => 'cancelled', 'user_id' => auth()->id(), 'note' => $reason]);

            Audit::log($startedCooking ? 'order.void_after_kitchen' : 'order.cancelled', $order, $before, $order->only(['status', 'payment_status']), $reason);
        });

        return $order->fresh();
    }

    public function openSession(DiningTable $table): TableSession
    {
        $session = TableSession::where('dining_table_id', $table->id)->where('status', 'open')->lockForUpdate()->first();
        if (! $session) {
            $session = TableSession::create([
                'tenant_id' => $table->tenant_id, 'branch_id' => $table->branch_id,
                'dining_table_id' => $table->id, 'status' => 'open', 'opened_at' => now(),
            ]);
        }
        if ($table->status === 'available') {
            $table->update(['status' => 'occupied']);
        }

        return $session;
    }

    public function closeSession(DiningTable $table): void
    {
        $session = $table->activeSession;
        if ($session) {
            $unpaid = $session->orders()->where('status', '!=', 'cancelled')->where('payment_status', '!=', 'paid')->exists();
            if ($unpaid) {
                throw new \DomainException('Masih ada pesanan meja ini yang belum dibayar.');
            }
            $session->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => auth()->id()]);
        }
        $table->update(['status' => 'available']);
    }

    protected function resolveCustomer(array $payload): ?Customer
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) ($payload['customer_phone'] ?? ''));
        if (strlen($phone) < 8) {
            return null;
        }
        $customer = Customer::firstOrCreate(['phone' => $phone], ['name' => $payload['customer_name'] ?? 'Pelanggan']);
        if (! empty($payload['marketing_consent']) && ! $customer->marketing_consent) {
            $customer->update(['marketing_consent' => true, 'consent_at' => now()]);
        }

        return $customer;
    }

    protected function pickupCode(Branch $branch): string
    {
        do {
            $code = (string) random_int(100000, 999999);
            $exists = Order::where('branch_id', $branch->id)->where('pickup_code', $code)
                ->whereNotIn('status', ['completed', 'cancelled'])->exists();
        } while ($exists);

        return $code;
    }

    /** Slot pickup tersedia berdasarkan jam buka & kapasitas per slot. */
    public function pickupSlots(Branch $branch, int $count = 16): array
    {
        $slotMin = max(5, (int) $branch->pickup_slot_minutes);
        $earliest = now()->addMinutes(20);
        $minutes = (int) ceil(($earliest->hour * 60 + $earliest->minute) / $slotMin) * $slotMin;
        $start = now()->startOfDay()->addMinutes($minutes);
        $close = now()->copy()->setTimeFromTimeString($branch->close_time);

        $booked = Order::where('branch_id', $branch->id)->where('channel', 'order_ahead')
            ->whereNotIn('status', ['cancelled'])->whereDate('pickup_at', now()->toDateString())
            ->selectRaw('pickup_at, COUNT(*) as c')->groupBy('pickup_at')->pluck('c', 'pickup_at')
            ->mapWithKeys(fn ($c, $k) => [\Illuminate\Support\Carbon::parse($k)->format('H:i') => $c]);

        $slots = [];
        for ($t = $start->copy(); $t <= $close && count($slots) < $count; $t->addMinutes($slotMin)) {
            if (! $branch->isOpenAt($t)) {
                continue;
            }
            $used = (int) ($booked[$t->format('H:i')] ?? 0);
            $slots[] = ['value' => $t->format('Y-m-d H:i'), 'label' => $t->format('H:i'), 'full' => $used >= $branch->pickup_capacity_per_slot];
        }

        return $slots;
    }
}
