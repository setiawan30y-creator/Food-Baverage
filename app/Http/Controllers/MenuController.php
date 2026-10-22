<?php

namespace App\Http\Controllers;

use App\Domain\Production\CostingService;
use App\Domain\Shared\Audit;
use App\Models\BranchMenu;
use App\Models\KitchenStation;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuModifier;
use App\Models\PriceHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Menu, kategori, modifier, harga & ketersediaan per cabang. @2026-Alifiandra */
class MenuController extends Controller
{
    public function index(Request $request, CostingService $costing)
    {
        $categories = MenuCategory::orderBy('sort')->orderBy('name')->get();
        $menus = Menu::with(['category', 'recipe'])
            ->when($request->q, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->category, fn ($q, $c) => $q->where('menu_category_id', $c))
            ->orderBy('name')->get();
        $settings = BranchMenu::where('branch_id', $this->branchId())->get()->keyBy('menu_id');
        $costs = $menus->mapWithKeys(fn ($m) => [$m->id => $costing->menuUnitCost($m)]);

        return view('menu.index', compact('categories', 'menus', 'settings', 'costs'));
    }

    public function create()
    {
        return view('menu.form', ['menu' => new Menu(['is_active' => true, 'prep_minutes' => 10, 'station_name' => 'Dapur']), ...$this->formData()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $menu = Menu::create($data);
        $this->savePhoto($request, $menu);
        PriceHistory::create(['menu_id' => $menu->id, 'old_price' => null, 'new_price' => $menu->price, 'user_id' => auth()->id()]);

        return redirect()->route('menus.edit', $menu)->with('success', 'Menu ditambahkan. Lengkapi pilihan tambahan & resepnya.');
    }

    public function edit(Menu $menu)
    {
        $menu->load('modifiers');
        $history = PriceHistory::where('menu_id', $menu->id)->with('user')->latest()->limit(10)->get();

        return view('menu.form', ['menu' => $menu, 'history' => $history, ...$this->formData()]);
    }

    public function update(Request $request, Menu $menu)
    {
        $data = $this->validated($request);
        if ((float) $data['price'] !== (float) $menu->price) {
            abort_unless($request->user()->hasAbility('menu.price'), 403, 'Anda tidak berwenang mengubah harga.');
            PriceHistory::create(['menu_id' => $menu->id, 'old_price' => $menu->price, 'new_price' => $data['price'], 'user_id' => auth()->id()]);
            Audit::log('menu.price_changed', $menu, ['price' => $menu->price], ['price' => $data['price']]);
        }
        $menu->update($data);
        $this->savePhoto($request, $menu);

        return back()->with('success', 'Menu disimpan.');
    }

    /** Harga khusus cabang, ketersediaan & tanda habis. */
    public function branchSetting(Request $request, Menu $menu)
    {
        $data = $request->validate([
            'price' => ['nullable', 'numeric', 'min:0'],
            'is_available' => ['nullable', 'boolean'],
            'sold_out' => ['nullable', 'boolean'],
        ]);
        $setting = BranchMenu::firstOrNew(['branch_id' => $this->branchId(), 'menu_id' => $menu->id]);
        if ($request->has('price')) {
            $newPrice = $data['price'] !== null ? (float) $data['price'] : null;
            if ($newPrice !== ($setting->price !== null ? (float) $setting->price : null)) {
                abort_unless($request->user()->hasAbility('menu.price'), 403, 'Anda tidak berwenang mengubah harga.');
                PriceHistory::create(['menu_id' => $menu->id, 'branch_id' => $this->branchId(), 'old_price' => $setting->price, 'new_price' => $newPrice, 'user_id' => auth()->id()]);
            }
            $setting->price = $newPrice;
        }
        if ($request->has('is_available')) {
            $setting->is_available = (bool) $data['is_available'];
        }
        if ($request->has('sold_out')) {
            $setting->sold_out = (bool) $data['sold_out'];
        }
        $setting->save();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Pengaturan cabang untuk '.$menu->name.' disimpan.');
    }

    public function storeModifier(Request $request, Menu $menu)
    {
        $data = $request->validate([
            'group_name' => ['required', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:60'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);
        $menu->modifiers()->create($data + ['tenant_id' => $menu->tenant_id]);

        return back()->with('success', 'Pilihan tambahan ditambahkan.');
    }

    public function destroyModifier(MenuModifier $modifier)
    {
        $modifier->update(['is_active' => false]);

        return back()->with('success', 'Pilihan tambahan dinonaktifkan.');
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'sort' => ['nullable', 'integer']]);
        MenuCategory::create($data + ['sort' => 0]);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function updateCategory(Request $request, MenuCategory $category)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'sort' => ['nullable', 'integer']]);
        $category->update($data + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('success', 'Kategori disimpan.');
    }

    protected function formData(): array
    {
        return [
            'categories' => MenuCategory::orderBy('sort')->orderBy('name')->get(),
            'stations' => KitchenStation::query()->pluck('name')->unique()->values(),
        ];
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sku' => ['nullable', 'string', 'max:40'],
            'menu_category_id' => ['nullable', Rule::exists('menu_categories', 'id')->where('tenant_id', tenancy()->tenantId())],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0'],
            'station_name' => ['required', 'string', 'max:60'],
            'prep_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'available_days' => ['nullable', 'array'],
            'available_days.*' => ['integer', 'between:1,7'],
            'available_from' => ['nullable', 'date_format:H:i'],
            'available_until' => ['nullable', 'date_format:H:i'],
            'photo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);
        $days = $data['available_days'] ?? [];
        $data['available_days'] = $days && count($days) < 7 ? implode(',', $days) : null;
        $data['is_active'] = $request->boolean('is_active');
        unset($data['photo']);

        return $data;
    }

    protected function savePhoto(Request $request, Menu $menu): void
    {
        if ($request->hasFile('photo')) {
            if ($menu->photo_path) {
                Storage::disk('local')->delete($menu->photo_path);
            }
            $menu->update(['photo_path' => $request->file('photo')->store('tenants/'.$menu->tenant_id.'/menus', 'local')]);
        }
    }
}
