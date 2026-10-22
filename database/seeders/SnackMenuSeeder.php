<?php

namespace Database\Seeders;

use App\Domain\Inventory\InventoryService;
use App\Domain\Production\ProductionService;
use App\Models\Branch;
use App\Models\InventoryItem;
use App\Models\KitchenStation;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuModifier;
use App\Models\Recipe;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Data contoh POS menu snack: kentang crispy, kentang spiral, corndog,
 * chicken popcorn, churros, sosis bakar, paket hemat, dan minuman —
 * lengkap dengan bahan, konversi kemasan, sub-recipe (racikan), resep, HPP,
 * stok awal, dan ilustrasi foto tiap menu (database/seeders/images/menus).
 *
 * Aman dijalankan ulang (tidak menggandakan data):
 *   php artisan db:seed --class=SnackMenuSeeder
 * Tenant tujuan: tenant aktif, atau SEED_TENANT=<slug>, atau tenant pertama.
 *
 * @2026-Alifiandra
 */
class SnackMenuSeeder extends Seeder
{
    /** @var array<string, InventoryItem> */
    protected array $items = [];

    public function run(): void
    {
        $tenant = tenancy()->tenant()
            ?? (env('SEED_TENANT') ? Tenant::where('slug', env('SEED_TENANT'))->first() : null)
            ?? Tenant::orderBy('id')->first();
        if (! $tenant) {
            $this->command?->error('Belum ada tenant. Jalankan: php artisan migrate --seed');

            return;
        }
        tenancy()->setTenant($tenant);
        $branches = Branch::where('is_active', true)->orderBy('id')->get();

        foreach ($branches as $branch) {
            KitchenStation::firstOrCreate(['branch_id' => $branch->id, 'name' => 'Penggorengan']);
            KitchenStation::firstOrCreate(['branch_id' => $branch->id, 'name' => 'Minuman']);
        }

        Supplier::firstOrCreate(['name' => 'Pemasok Kentang & Frozen Food'], ['phone' => '080000000001', 'lead_time_days' => 2]);
        Supplier::firstOrCreate(['name' => 'Pemasok Bumbu, Saus & Kemasan'], ['phone' => '080000000002', 'lead_time_days' => 1]);

        $this->seedIngredients($branches);
        $this->seedSubRecipes();
        $this->seedMenus();
        $this->produceSubRecipes($branches);

        $this->command?->info('Menu snack contoh siap untuk tenant: '.$tenant->name);
    }

    /** Bahan baku: [kunci => [nama, kategori, satuan dasar, HPP/satuan dasar, stok awal, stok minimum, konversi]] */
    protected function seedIngredients($branches): void
    {
        $raw = [
            'kentang_beku' => ['Kentang beku shoestring', 'Kentang', 'gram', 32, 20000, 4000, ['pack' => 1000, 'karton' => 10000]],
            'kentang_segar' => ['Kentang segar (spiral)', 'Kentang', 'pcs', 2500, 120, 30, ['kilogram' => 5]],
            'sosis' => ['Sosis jumbo', 'Protein', 'pcs', 3500, 150, 40, ['pack' => 10]],
            'mozza' => ['Keju mozzarella', 'Keju', 'gram', 110, 4000, 1000, ['kilogram' => 1000]],
            'cheddar' => ['Keju cheddar blok', 'Keju', 'gram', 85, 2000, 500, ['kilogram' => 1000, 'box' => 2000]],
            'premix' => ['Tepung premix corndog', 'Tepung', 'gram', 28, 10000, 2000, ['kilogram' => 1000]],
            'panir' => ['Tepung panir', 'Tepung', 'gram', 30, 5000, 1000, ['kilogram' => 1000]],
            'terigu' => ['Tepung terigu', 'Tepung', 'gram', 13, 5000, 1000, ['kilogram' => 1000]],
            'telur' => ['Telur ayam', 'Protein', 'butir', 2200, 90, 30, ['tray' => 30]],
            'susu' => ['Susu cair UHT', 'Bahan cair', 'mililiter', 18, 4000, 1000, ['liter' => 1000, 'karton' => 12000]],
            'ayam' => ['Ayam fillet', 'Protein', 'gram', 65, 5000, 1000, ['kilogram' => 1000]],
            'minyak' => ['Minyak goreng', 'Bahan pokok', 'mililiter', 18, 20000, 4000, ['liter' => 1000, 'jeriken' => 18000]],
            'bubuk_keju' => ['Bubuk keju', 'Bumbu', 'gram', 90, 1500, 300, ['pack' => 250]],
            'bubuk_balado' => ['Bubuk balado', 'Bumbu', 'gram', 80, 1500, 300, ['pack' => 250]],
            'bubuk_bbq' => ['Bubuk BBQ', 'Bumbu', 'gram', 85, 1000, 200, ['pack' => 250]],
            'bubuk_jagung' => ['Bubuk jagung bakar', 'Bumbu', 'gram', 85, 1000, 200, ['pack' => 250]],
            'kayu_manis' => ['Bubuk kayu manis', 'Bumbu', 'gram', 120, 500, 100, ['pack' => 100]],
            'garam' => ['Garam', 'Bumbu', 'gram', 8, 2000, 200, ['kilogram' => 1000]],
            'gula' => ['Gula pasir', 'Bahan pokok', 'gram', 17, 6000, 1000, ['kilogram' => 1000]],
            'saus_sambal' => ['Saus sambal', 'Saus', 'gram', 25, 5000, 1000, ['jeriken' => 5000]],
            'saus_tomat' => ['Saus tomat', 'Saus', 'gram', 22, 5000, 1000, ['jeriken' => 5000]],
            'mayones' => ['Mayones', 'Saus', 'gram', 45, 3000, 500, ['pack' => 1000]],
            'mustard' => ['Mustard', 'Saus', 'gram', 60, 1000, 200, ['botol' => 250]],
            'teh' => ['Teh celup', 'Minuman', 'sachet', 300, 200, 50, ['box' => 25]],
            'jeruk' => ['Jeruk peras', 'Minuman', 'buah', 1500, 60, 20, ['kilogram' => 8]],
            'lemon' => ['Lemon', 'Minuman', 'buah', 2000, 40, 10, ['kilogram' => 6]],
            'es' => ['Es batu', 'Minuman', 'gram', 1, 20000, 3000, ['karung' => 10000]],
            'air_mineral' => ['Air mineral 600 ml', 'Minuman', 'botol', 2500, 48, 12, ['karton' => 24]],
            'tusuk' => ['Tusuk kayu', 'Kemasan', 'pcs', 100, 500, 100, ['pack' => 100]],
            'cup' => ['Cup kertas snack', 'Kemasan', 'pcs', 600, 500, 100, ['pack' => 50]],
            'gelas' => ['Gelas plastik', 'Kemasan', 'pcs', 500, 300, 50, ['pack' => 50]],
        ];

        $inventory = app(InventoryService::class);
        foreach ($raw as $key => [$name, $category, $unit, $cost, $stock, $min, $conversions]) {
            $item = InventoryItem::firstOrCreate(
                ['name' => $name],
                ['category' => $category, 'type' => 'raw', 'base_unit' => $unit, 'avg_cost' => $cost, 'min_stock' => $min]
            );
            foreach ($conversions as $u => $factor) {
                $item->conversions()->firstOrCreate(['unit' => $u], ['factor' => $factor]);
            }
            foreach ($branches as $branch) {
                $hasOpening = StockMovement::where('branch_id', $branch->id)->where('inventory_item_id', $item->id)->exists();
                if (! $hasOpening) {
                    $inventory->post($item, $branch->id, 'opening', $stock, (float) $item->avg_cost ?: $cost, null, 'Saldo awal (data contoh)');
                }
            }
            $this->items[$key] = $item;
        }
    }

    /** Racikan (sub-recipe): stok bertambah lewat produksi batch, HPP dari resepnya. */
    protected function seedSubRecipes(): void
    {
        $subs = [
            'adonan_corndog' => ['Adonan corndog', 900, 300, ['premix' => 500, 'telur' => 2, 'susu' => 300]],
            'saus_keju' => ['Saus keju racikan', 650, 200, ['cheddar' => 300, 'susu' => 400, 'terigu' => 20, 'garam' => 3]],
            'tabur_keju' => ['Bumbu tabur keju racikan', 500, 100, ['bubuk_keju' => 450, 'garam' => 30, 'gula' => 20]],
            'gula_kayu_manis' => ['Gula kayu manis racikan', 500, 100, ['gula' => 450, 'kayu_manis' => 50]],
            'adonan_churros' => ['Adonan churros', 750, 300, ['terigu' => 500, 'telur' => 2, 'minyak' => 50, 'garam' => 3]],
        ];

        foreach ($subs as $key => [$name, $yield, $min, $bom]) {
            $item = InventoryItem::firstOrCreate(
                ['name' => $name],
                ['category' => 'Racikan', 'type' => 'sub_recipe', 'base_unit' => 'gram', 'min_stock' => $min]
            );
            $this->items[$key] = $item;
            if (! Recipe::where('type', 'sub_recipe')->where('output_item_id', $item->id)->where('is_active', true)->exists()) {
                $this->recipe('sub_recipe', $name, $yield, $bom, output: $item);
            }
        }
    }

    protected function seedMenus(): void
    {
        $categories = [];
        foreach (['Kentang' => 1, 'Corndog' => 2, 'Camilan Lain' => 3, 'Paket Hemat' => 4, 'Minuman' => 5] as $name => $sort) {
            $categories[$name] = MenuCategory::firstOrCreate(['name' => $name], ['sort' => $sort]);
        }

        $sizeMods = [['Ukuran', 'Reguler', 0], ['Ukuran', 'Large', 5000]];
        $sauceMods = [['Saus', 'Saus tomat', 0], ['Saus', 'Saus sambal', 0], ['Saus', 'Mayones', 0], ['Saus', 'Mustard', 1000]];
        $cheeseExtra = [['Tambahan', 'Saus keju', 4000]];
        $seasonMods = [['Bumbu', 'Keju', 0], ['Bumbu', 'Balado', 0], ['Bumbu', 'BBQ', 0], ['Bumbu', 'Jagung bakar', 0]];
        $sugarMods = [['Gula', 'Normal', 0], ['Gula', 'Kurang manis', 0], ['Gula', 'Tanpa gula', 0]];

        // [nama, kategori, harga, stasiun, menit siap, deskripsi, resep per porsi, pilihan tambahan]
        $menus = [
            ['Kentang Crispy Original', 'Kentang', 12000, 'Penggorengan', 6, 'Kentang goreng renyah dengan taburan garam.',
                ['kentang_beku' => 150, 'minyak' => 40, 'garam' => 2, 'cup' => 1], [...$sizeMods, ...$sauceMods]],
            ['Kentang Crispy Keju', 'Kentang', 15000, 'Penggorengan', 6, 'Kentang goreng dengan bumbu tabur keju gurih.',
                ['kentang_beku' => 150, 'minyak' => 40, 'tabur_keju' => 10, 'cup' => 1], [...$sizeMods, ...$cheeseExtra]],
            ['Kentang Crispy Balado', 'Kentang', 15000, 'Penggorengan', 6, 'Kentang goreng dengan bumbu balado pedas manis.',
                ['kentang_beku' => 150, 'minyak' => 40, 'bubuk_balado' => 10, 'cup' => 1], [...$sizeMods, ['Level pedas', 'Sedang', 0], ['Level pedas', 'Pedas', 0]]],
            ['Kentang Spiral', 'Kentang', 13000, 'Penggorengan', 8, 'Kentang utuh dipotong spiral, digoreng renyah di tusuk.',
                ['kentang_segar' => 1, 'minyak' => 60, 'bubuk_keju' => 8, 'tusuk' => 1], [...$seasonMods, ...$cheeseExtra]],
            ['Kentang Spiral Keju Lumer', 'Kentang', 17000, 'Penggorengan', 9, 'Kentang spiral disiram saus keju hangat.',
                ['kentang_segar' => 1, 'minyak' => 60, 'saus_keju' => 40, 'tusuk' => 1], []],

            ['Corndog Sosis', 'Corndog', 15000, 'Penggorengan', 7, 'Sosis jumbo berbalut adonan dan tepung panir.',
                ['sosis' => 1, 'adonan_corndog' => 90, 'panir' => 25, 'minyak' => 60, 'tusuk' => 1], [...$sauceMods, ['Tambahan', 'Gula tabur', 0], ...$cheeseExtra]],
            ['Corndog Mozzarella', 'Corndog', 18000, 'Penggorengan', 7, 'Isi keju mozzarella yang melar saat digigit.',
                ['mozza' => 60, 'adonan_corndog' => 90, 'panir' => 25, 'minyak' => 60, 'tusuk' => 1], [...$sauceMods, ['Tambahan', 'Gula tabur', 0], ...$cheeseExtra]],
            ['Corndog Mix Sosis Mozza', 'Corndog', 20000, 'Penggorengan', 8, 'Setengah sosis, setengah mozzarella.',
                ['sosis' => 0.5, 'mozza' => 35, 'adonan_corndog' => 100, 'panir' => 25, 'minyak' => 60, 'tusuk' => 1], [...$sauceMods, ...$cheeseExtra]],
            ['Corndog Kentang', 'Corndog', 22000, 'Penggorengan', 9, 'Corndog sosis-mozza berbalut potongan kentang.',
                ['sosis' => 1, 'mozza' => 30, 'adonan_corndog' => 100, 'kentang_beku' => 60, 'minyak' => 70, 'tusuk' => 1], [...$sauceMods, ...$cheeseExtra]],

            ['Chicken Popcorn', 'Camilan Lain', 17000, 'Penggorengan', 8, 'Potongan ayam fillet goreng tepung, renyah.',
                ['ayam' => 120, 'terigu' => 30, 'panir' => 20, 'minyak' => 50, 'garam' => 2, 'cup' => 1], [['Bumbu', 'Original', 0], ['Bumbu', 'Keju', 0], ['Bumbu', 'Balado', 0], ...$sauceMods]],
            ['Sosis Bakar Jumbo', 'Camilan Lain', 12000, 'Penggorengan', 6, 'Sosis jumbo bakar dengan olesan saus.',
                ['sosis' => 1, 'saus_sambal' => 15, 'minyak' => 10, 'tusuk' => 1], [['Saus', 'Pedas', 0], ['Saus', 'BBQ', 0]]],
            ['Churros Gula Kayu Manis', 'Camilan Lain', 12000, 'Penggorengan', 7, 'Churros renyah dengan gula kayu manis.',
                ['adonan_churros' => 100, 'minyak' => 50, 'gula_kayu_manis' => 15, 'cup' => 1], $cheeseExtra],

            ['Paket Hemat Snack', 'Paket Hemat', 30000, 'Penggorengan', 9, 'Kentang Crispy Original + Corndog Sosis + Es Teh Manis.',
                ['kentang_beku' => 150, 'garam' => 2, 'cup' => 1, 'sosis' => 1, 'adonan_corndog' => 90, 'panir' => 25, 'minyak' => 100, 'tusuk' => 1,
                    'teh' => 1, 'gula' => 20, 'es' => 150, 'gelas' => 1], $sauceMods],

            ['Es Teh Manis', 'Minuman', 5000, 'Minuman', 2, null, ['teh' => 1, 'gula' => 20, 'es' => 150, 'gelas' => 1], $sugarMods],
            ['Es Jeruk', 'Minuman', 8000, 'Minuman', 3, null, ['jeruk' => 2, 'gula' => 20, 'es' => 150, 'gelas' => 1], $sugarMods],
            ['Lemon Tea', 'Minuman', 8000, 'Minuman', 3, null, ['teh' => 1, 'lemon' => 0.5, 'gula' => 20, 'es' => 150, 'gelas' => 1], $sugarMods],
            ['Air Mineral 600 ml', 'Minuman', 4000, 'Minuman', 0, null, ['air_mineral' => 1], []],
        ];

        foreach ($menus as [$name, $cat, $price, $station, $prep, $desc, $bom, $mods]) {
            $menu = Menu::firstOrCreate(['name' => $name], [
                'menu_category_id' => $categories[$cat]->id, 'price' => $price, 'station_name' => $station,
                'prep_minutes' => $prep, 'description' => $desc, 'is_active' => true,
            ]);
            if ($menu->wasRecentlyCreated) {
                foreach ($mods as [$group, $label, $modPrice]) {
                    MenuModifier::create(['menu_id' => $menu->id, 'group_name' => $group, 'name' => $label, 'price' => $modPrice]);
                }
            }
            if (! Recipe::where('type', 'menu')->where('menu_id', $menu->id)->where('is_active', true)->exists()) {
                $this->recipe('menu', $name, 1, $bom, menu: $menu);
            }
            $this->attachPhoto($menu);
        }
    }

    /** Pasang ilustrasi contoh (database/seeders/images/menus/<slug>.webp) bila menu belum punya foto. */
    protected function attachPhoto(Menu $menu): void
    {
        $source = database_path('seeders/images/menus/'.Str::slug($menu->name).'.webp');
        if ($menu->photo_path || ! is_file($source)) {
            return;
        }
        $path = 'tenants/'.$menu->tenant_id.'/menus/contoh-'.Str::slug($menu->name).'.webp';
        Storage::disk('local')->put($path, file_get_contents($source));
        $menu->update(['photo_path' => $path]);
    }

    /** Stok awal racikan dibuat lewat batch produksi agar HPP & susut tercatat. */
    protected function produceSubRecipes($branches): void
    {
        $plan = [
            // [kunci, pengali resep, hasil aktual, alasan selisih]
            ['adonan_corndog', 3, 2650, 'Adonan tertinggal di wadah'],
            ['saus_keju', 2, 1300, null],
            ['tabur_keju', 1, 500, null],
            ['gula_kayu_manis', 1, 500, null],
            ['adonan_churros', 2, 1450, 'Adonan tertinggal di spuit'],
        ];
        $inventory = app(InventoryService::class);
        $production = app(ProductionService::class);

        foreach ($branches as $branch) {
            foreach ($plan as [$key, $multiplier, $actual, $reason]) {
                $item = $this->items[$key];
                if ($inventory->balance($item->id, $branch->id) > 0) {
                    continue;
                }
                $recipe = Recipe::where('type', 'sub_recipe')->where('output_item_id', $item->id)->where('is_active', true)->with('items')->first();
                $consumptions = $recipe->items->mapWithKeys(fn ($l) => [$l->inventory_item_id => (float) $l->qty * $multiplier])->all();
                $production->produce($recipe, $branch->id, $multiplier, $actual, $consumptions, $reason);
            }
        }
    }

    protected function recipe(string $type, string $name, float $yield, array $bom, ?InventoryItem $output = null, ?Menu $menu = null): Recipe
    {
        $recipe = Recipe::create([
            'type' => $type, 'name' => $name, 'yield_qty' => $yield, 'version' => 1, 'effective_from' => today(),
            'output_item_id' => $output?->id, 'menu_id' => $menu?->id, 'is_active' => true,
        ]);
        foreach ($bom as $key => $qty) {
            $recipe->items()->create(['inventory_item_id' => $this->items[$key]->id, 'qty' => $qty]);
        }

        return $recipe;
    }
}
