<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryService;
use App\Domain\Production\CostingService;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Master bahan baku & bahan hasil produksi (sub-recipe). @2026-Alifiandra */
class InventoryItemController extends Controller
{
    public function index(Request $request, InventoryService $inventory, CostingService $costing)
    {
        $items = InventoryItem::with('conversions')
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->q, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('type')->orderBy('name')->get();
        $balances = $inventory->balances($this->branchId());
        $costs = $items->mapWithKeys(fn ($i) => [$i->id => $costing->itemUnitCost($i)]);

        return view('inventory.items', compact('items', 'balances', 'costs'));
    }

    public function create()
    {
        return view('inventory.item-form', ['item' => new InventoryItem(['type' => 'raw', 'base_unit' => 'gram', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        $item = InventoryItem::create($this->validated($request));
        $this->syncConversions($request, $item);

        return redirect()->route('inventory.items.index')->with('success', 'Bahan ditambahkan.');
    }

    public function edit(InventoryItem $item)
    {
        $item->load('conversions');

        return view('inventory.item-form', compact('item'));
    }

    public function update(Request $request, InventoryItem $item)
    {
        $item->update($this->validated($request, $item));
        $this->syncConversions($request, $item);

        return redirect()->route('inventory.items.index')->with('success', 'Bahan disimpan.');
    }

    protected function validated(Request $request, ?InventoryItem $item = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:60'],
            'type' => ['required', Rule::in(['raw', 'sub_recipe'])],
            'base_unit' => ['required', 'string', 'max:20'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
        ]);
        $data['min_stock'] = $data['min_stock'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);
        if ($item && $item->type !== $data['type'] && $item->movements()->exists()) {
            throw new \DomainException('Tipe bahan tidak dapat diubah karena sudah memiliki pergerakan stok.');
        }

        return $data;
    }

    protected function syncConversions(Request $request, InventoryItem $item): void
    {
        $rows = collect($request->input('conversions', []))
            ->filter(fn ($r) => ! empty($r['unit']) && (float) ($r['factor'] ?? 0) > 0);
        $item->conversions()->delete();
        foreach ($rows as $r) {
            if ($r['unit'] === $item->base_unit) {
                continue;
            }
            $item->conversions()->updateOrCreate(['unit' => trim($r['unit'])], ['factor' => (float) $r['factor']]);
        }
    }
}
