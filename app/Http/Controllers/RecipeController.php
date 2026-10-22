<?php

namespace App\Http\Controllers;

use App\Domain\Production\CostingService;
use App\Domain\Shared\Audit;
use App\Models\BranchMenu;
use App\Models\InventoryItem;
use App\Models\Menu;
use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Resep menu & sub-recipe (saus, bumbu, sambal, marinasi, adonan, kaldu).
 * Perubahan resep membuat VERSI baru — snapshot HPP transaksi lama tetap.
 * @2026-Alifiandra
 */
class RecipeController extends Controller
{
    public function index(CostingService $costing)
    {
        $recipes = Recipe::with(['menu', 'outputItem', 'items'])->where('is_active', true)->orderBy('type')->orderBy('name')->get();
        $prices = BranchMenu::where('branch_id', $this->branchId())->pluck('price', 'menu_id');
        $rows = $recipes->map(function ($r) use ($costing, $prices) {
            $cost = $costing->recipeUnitCost($r);
            $price = $r->menu ? (float) ($prices[$r->menu_id] ?? $r->menu->price) : null;

            return ['recipe' => $r, 'cost' => $cost, 'price' => $price,
                'margin' => $price ? ($price - $cost) / max($price, 1) * 100 : null];
        });
        $menusWithout = Menu::where('is_active', true)->whereDoesntHave('recipes', fn ($q) => $q->where('is_active', true))->orderBy('name')->get();

        return view('recipes.index', compact('rows', 'menusWithout'));
    }

    public function create(Request $request)
    {
        $type = $request->query('type', 'menu') === 'sub_recipe' ? 'sub_recipe' : 'menu';
        $recipe = new Recipe(['type' => $type, 'yield_qty' => 1, 'menu_id' => $request->query('menu_id')]);

        return view('recipes.form', ['recipe' => $recipe, 'lines' => [], ...$this->formData()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $recipe = DB::transaction(fn () => $this->persist($data));

        return redirect()->route('recipes.edit', $recipe)->with('success', 'Resep disimpan.');
    }

    public function edit(Recipe $recipe, CostingService $costing)
    {
        $recipe->load('items.item');
        $lines = $recipe->items->map(fn ($i) => ['inventory_item_id' => $i->inventory_item_id, 'qty' => (float) $i->qty])->all();
        $breakdown = $costing->breakdown($recipe);
        $unitCost = $costing->recipeUnitCost($recipe);
        $versions = Recipe::where('type', $recipe->type)
            ->where($recipe->type === 'menu' ? 'menu_id' : 'output_item_id', $recipe->type === 'menu' ? $recipe->menu_id : $recipe->output_item_id)
            ->orderByDesc('version')->get();

        return view('recipes.form', compact('recipe', 'lines', 'breakdown', 'unitCost', 'versions') + $this->formData());
    }

    /** Simpan sebagai versi baru; versi lama dinonaktifkan (riwayat tetap). */
    public function update(Request $request, Recipe $recipe)
    {
        $data = $this->validated($request, $recipe);
        $new = DB::transaction(function () use ($data, $recipe) {
            $recipe->update(['is_active' => false]);

            return $this->persist($data, $recipe->version + 1);
        });
        Audit::log('recipe.new_version', $new, ['version' => $recipe->version], ['version' => $new->version]);

        return redirect()->route('recipes.edit', $new)->with('success', 'Resep versi '.$new->version.' disimpan.');
    }

    protected function persist(array $data, int $version = 1): Recipe
    {
        $recipe = Recipe::create([
            'type' => $data['type'],
            'menu_id' => $data['type'] === 'menu' ? $data['menu_id'] : null,
            'output_item_id' => $data['type'] === 'sub_recipe' ? $data['output_item_id'] : null,
            'name' => $data['name'],
            'yield_qty' => $data['yield_qty'],
            'expected_waste_percent' => $data['expected_waste_percent'] ?? 0,
            'version' => $version,
            'effective_from' => today(),
            'note' => $data['note'] ?? null,
        ]);
        foreach ($data['lines'] as $line) {
            $recipe->items()->create(['inventory_item_id' => $line['inventory_item_id'], 'qty' => $line['qty']]);
        }

        return $recipe;
    }

    protected function validated(Request $request, ?Recipe $current = null): array
    {
        $tenant = tenancy()->tenantId();
        $data = $request->validate([
            'type' => ['required', Rule::in(['menu', 'sub_recipe'])],
            'name' => ['required', 'string', 'max:120'],
            'menu_id' => ['required_if:type,menu', 'nullable', Rule::exists('menus', 'id')->where('tenant_id', $tenant)],
            'output_item_id' => ['required_if:type,sub_recipe', 'nullable', Rule::exists('inventory_items', 'id')->where('tenant_id', $tenant)->where('type', 'sub_recipe')],
            'yield_qty' => ['required', 'numeric', 'gt:0'],
            'expected_waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.inventory_item_id' => ['required', Rule::exists('inventory_items', 'id')->where('tenant_id', $tenant)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);

        // Cegah resep memakai dirinya sendiri sebagai bahan (siklus)
        if ($data['type'] === 'sub_recipe') {
            foreach ($data['lines'] as $line) {
                if ((int) $line['inventory_item_id'] === (int) $data['output_item_id']) {
                    throw new \DomainException('Sub-recipe tidak boleh memakai hasilnya sendiri sebagai bahan.');
                }
            }
        }
        if (! $current) {
            $exists = Recipe::where('is_active', true)->where('type', $data['type'])
                ->when($data['type'] === 'menu', fn ($q) => $q->where('menu_id', $data['menu_id']))
                ->when($data['type'] === 'sub_recipe', fn ($q) => $q->where('output_item_id', $data['output_item_id']))->exists();
            if ($exists) {
                throw new \DomainException('Resep aktif untuk item ini sudah ada. Ubah resep tersebut untuk membuat versi baru.');
            }
        }

        return $data;
    }

    protected function formData(): array
    {
        return [
            'menus' => Menu::where('is_active', true)->orderBy('name')->get(),
            'subItems' => InventoryItem::where('type', 'sub_recipe')->where('is_active', true)->orderBy('name')->get(),
            'items' => InventoryItem::where('is_active', true)->orderBy('type')->orderBy('name')->get(),
        ];
    }
}
