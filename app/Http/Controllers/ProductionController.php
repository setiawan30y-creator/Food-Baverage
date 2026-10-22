<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\InventoryService;
use App\Domain\Production\ProductionService;
use App\Models\ProductionBatch;
use App\Models\Recipe;
use Illuminate\Http\Request;

/** Produksi batch sub-recipe dengan yield aktual & susut. @2026-Alifiandra */
class ProductionController extends Controller
{
    public function index()
    {
        $batches = ProductionBatch::forBranch()->with(['recipe.outputItem', 'user'])->latest('produced_at')->paginate(25);
        $recipes = Recipe::where('type', 'sub_recipe')->where('is_active', true)->with('outputItem')->orderBy('name')->get();

        return view('production.index', compact('batches', 'recipes'));
    }

    public function create(Request $request, InventoryService $inventory)
    {
        $recipe = Recipe::where('type', 'sub_recipe')->where('is_active', true)->with(['items.item', 'outputItem'])->findOrFail($request->query('recipe_id'));
        $balances = $inventory->balances($this->branchId());

        return view('production.create', compact('recipe', 'balances'));
    }

    public function store(Request $request, ProductionService $production)
    {
        $data = $request->validate([
            'recipe_id' => ['required', 'integer'],
            'multiplier' => ['required', 'numeric', 'gt:0', 'max:1000'],
            'actual_yield' => ['required', 'numeric', 'gt:0'],
            'consumptions' => ['required', 'array'],
            'consumptions.*' => ['nullable', 'numeric', 'min:0'],
            'variance_reason' => ['nullable', 'string', 'max:200'],
        ]);
        $recipe = Recipe::where('type', 'sub_recipe')->findOrFail($data['recipe_id']);
        $planned = (float) $recipe->yield_qty * (float) $data['multiplier'];
        if (abs($planned - (float) $data['actual_yield']) > 0.0001 && ! trim((string) ($data['variance_reason'] ?? ''))) {
            return back()->withInput()->with('error', 'Yield aktual berbeda dari rencana — alasan selisih wajib diisi.');
        }

        $batch = $production->produce($recipe, $this->branchId(), (float) $data['multiplier'], (float) $data['actual_yield'],
            array_map('floatval', array_filter($data['consumptions'], fn ($v) => $v !== null && $v !== '')), $data['variance_reason'] ?? null);

        return redirect()->route('production.show', $batch)->with('success', 'Batch '.$batch->number.' dicatat.');
    }

    public function show(ProductionBatch $batch)
    {
        $this->ensureBranch($batch);
        $batch->load('consumptions.item', 'recipe.outputItem', 'user');

        return view('production.show', compact('batch'));
    }
}
