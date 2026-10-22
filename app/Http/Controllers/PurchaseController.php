<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryService;
use App\Domain\Shared\Audit;
use App\Domain\Shared\SequenceService;
use App\Models\InventoryItem;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Pembelian → penerimaan barang (stok & HPP rata-rata) → pembayaran utang supplier. @2026-Alifiandra */
class PurchaseController extends Controller
{
    public function index()
    {
        $purchases = Purchase::forBranch()->with('supplier')->latest('date')->latest('id')->paginate(25);

        return view('purchasing.index', compact('purchases'));
    }

    public function create()
    {
        return view('purchasing.create', [
            'suppliers' => Supplier::orderBy('name')->get(),
            'items' => InventoryItem::with('conversions')->where('is_active', true)->where('type', 'raw')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, SequenceService $seq)
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('tenant_id', tenancy()->tenantId())],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['required', 'string', 'max:30'],
            'lines.*.price' => ['required', 'numeric', 'min:0'],
        ]);

        $purchase = DB::transaction(function () use ($data, $seq) {
            $purchase = Purchase::create([
                'branch_id' => $this->branchId(),
                'supplier_id' => $data['supplier_id'] ?? null,
                'number' => $seq->next(tenancy()->tenantId(), $this->branchId(), 'purchase', 'PO'),
                'status' => 'draft',
                'date' => $data['date'],
                'note' => $data['note'] ?? null,
                'user_id' => auth()->id(),
            ]);
            $total = 0;
            $items = InventoryItem::with('conversions')->whereIn('id', collect($data['lines'])->pluck('inventory_item_id'))->get()->keyBy('id');
            foreach ($data['lines'] as $line) {
                $item = $items->get($line['inventory_item_id']);
                abort_unless($item, 422);
                $factor = $item->factorFor($line['unit']);
                $subtotal = round((float) $line['qty'] * (float) $line['price'], 2);
                $purchase->items()->create([
                    'inventory_item_id' => $item->id,
                    'qty' => $line['qty'],
                    'unit' => $line['unit'],
                    'factor' => $factor,
                    'base_qty' => (float) $line['qty'] * $factor,
                    'price' => $line['price'],
                    'subtotal' => $subtotal,
                ]);
                $total += $subtotal;
            }
            $purchase->update(['total' => $total]);

            return $purchase;
        });

        if ($request->input('action') === 'receive') {
            app(InventoryService::class)->receivePurchase($purchase);
        }

        return redirect()->route('purchases.show', $purchase)->with('success', 'Pembelian disimpan.');
    }

    public function show(Purchase $purchase)
    {
        $this->ensureBranch($purchase);
        $purchase->load('items.item', 'supplier');
        $priceHistory = \App\Models\PurchaseItem::query()
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.tenant_id', tenancy()->tenantId())->where('purchases.status', 'received')
            ->whereIn('purchase_items.inventory_item_id', $purchase->items->pluck('inventory_item_id'))
            ->selectRaw('purchase_items.inventory_item_id, MIN(purchase_items.price / purchase_items.factor) as min_price, MAX(purchase_items.price / purchase_items.factor) as max_price')
            ->groupBy('purchase_items.inventory_item_id')->get()->keyBy('inventory_item_id');

        return view('purchasing.show', compact('purchase', 'priceHistory'));
    }

    public function receive(Purchase $purchase, InventoryService $inventory)
    {
        $this->ensureBranch($purchase);
        $inventory->receivePurchase($purchase);

        return back()->with('success', 'Barang diterima, stok & HPP rata-rata diperbarui.');
    }

    public function pay(Request $request, Purchase $purchase)
    {
        $this->ensureBranch($purchase);
        $data = $request->validate(['amount' => ['required', 'numeric', 'gt:0']]);
        $paid = min((float) $purchase->total, (float) $purchase->paid_amount + (float) $data['amount']);
        $purchase->update([
            'paid_amount' => $paid,
            'payment_status' => $paid >= (float) $purchase->total ? 'paid' : 'partial',
        ]);
        Audit::log('purchase.payment', $purchase, null, ['amount' => $data['amount'], 'paid_total' => $paid]);

        return back()->with('success', 'Pembayaran supplier dicatat.');
    }

    public function cancel(Purchase $purchase)
    {
        $this->ensureBranch($purchase);
        abort_unless($purchase->status === 'draft', 422, 'Pembelian yang sudah diterima tidak dapat dibatalkan; gunakan retur/penyesuaian.');
        $purchase->update(['status' => 'cancelled']);
        Audit::log('purchase.cancelled', $purchase);

        return back()->with('success', 'Pembelian dibatalkan.');
    }
}
