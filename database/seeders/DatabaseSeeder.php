<?php

namespace Database\Seeders;

use App\Domain\Inventory\InventoryService;
use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\KitchenStation;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuModifier;
use App\Models\Recipe;
use App\Models\ShiftTemplate;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\ThemeSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data contoh warung makan untuk mencoba aplikasi.
 * Semua nama bersifat generik/netral.
 * @2026-Alifiandra
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create([
            'name' => 'Warung Makan Contoh',
            'slug' => 'warung-contoh',
            'business_type' => 'warung_makan',
            'plan' => 'pro',
            'status' => 'active',
            'max_branches' => 5,
            'max_users' => 50,
        ]);
        tenancy()->setTenant($tenant);

        $branch = Branch::create([
            'name' => 'Cabang Utama', 'code' => 'UTM', 'address' => 'Alamat cabang utama',
            'phone' => '080000000000', 'latitude' => -6.2000000, 'longitude' => 106.8166660, 'geofence_radius' => 150,
            'open_time' => '07:00', 'close_time' => '22:00', 'tax_percent' => 0, 'service_percent' => 0,
        ]);
        tenancy()->setBranch($branch);

        CompanyProfile::create([
            'name' => 'Warung Makan Contoh', 'address' => 'Alamat usaha', 'phone' => '080000000000',
            'whatsapp' => '080000000000', 'email' => 'info@contoh.test',
            'receipt_header' => 'Terima kasih telah berkunjung', 'receipt_footer' => 'Simpan nota ini sebagai bukti pembayaran',
        ]);
        ThemeSetting::create([]);

        $dapur = KitchenStation::create(['branch_id' => $branch->id, 'name' => 'Dapur']);
        KitchenStation::create(['branch_id' => $branch->id, 'name' => 'Minuman']);

        $users = [
            ['Pemilik Usaha', 'owner@contoh.test', 'owner', null],
            ['Manajer Outlet', 'manajer@contoh.test', 'manager', $branch->id],
            ['Kasir Satu', 'kasir@contoh.test', 'cashier', $branch->id],
            ['Juru Masak', 'dapur@contoh.test', 'kitchen', $branch->id],
            ['Staf Gudang', 'gudang@contoh.test', 'warehouse', $branch->id],
        ];
        $userModels = [];
        foreach ($users as [$name, $email, $role, $branchId]) {
            $userModels[$role] = User::create([
                'tenant_id' => $tenant->id, 'branch_id' => $branchId, 'name' => $name, 'email' => $email,
                'role' => $role, 'password' => 'password', 'is_active' => true,
            ]);
        }

        // Kode papan shift
        foreach ([
            ['P', 'Pagi', '07:00', '15:00', true, '#FDE68A'],
            ['S', 'Siang', '11:00', '19:00', true, '#BFDBFE'],
            ['M', 'Malam', '14:00', '22:00', true, '#C7D2FE'],
            ['OFF', 'Libur', null, null, false, '#E5E7EB'],
            ['C', 'Cuti', null, null, false, '#FBCFE8'],
            ['I', 'Izin', null, null, false, '#FED7AA'],
            ['L', 'Libur nasional', null, null, false, '#FECACA'],
        ] as [$code, $name, $start, $end, $working, $color]) {
            ShiftTemplate::create(compact('code', 'name') + ['start_time' => $start, 'end_time' => $end, 'is_working' => $working, 'color' => $color]);
        }

        // Karyawan
        $emps = [
            ['K001', 'Manajer Outlet', 'manajer', 'monthly', 4500000, 'manager'],
            ['K002', 'Kasir Satu', 'kasir', 'daily', 120000, 'cashier'],
            ['K003', 'Juru Masak', 'koki', 'shift', 130000, 'kitchen'],
            ['K004', 'Pramusaji Satu', 'pramusaji', 'hourly', 15000, null],
            ['K005', 'Staf Gudang', 'gudang', 'daily', 110000, 'warehouse'],
        ];
        foreach ($emps as [$code, $name, $pos, $method, $rate, $role]) {
            Employee::create([
                'branch_id' => $branch->id, 'user_id' => $role ? $userModels[$role]->id : null, 'code' => $code, 'name' => $name,
                'position' => $pos, 'status' => 'active', 'start_date' => now()->subMonths(6)->toDateString(),
                'wage_method' => $method, 'wage_rate' => $rate, 'allowance' => $method === 'monthly' ? 300000 : 0, 'overtime_rate' => 20000,
            ]);
        }

        // Meja
        foreach (range(1, 8) as $i) {
            DiningTable::create(['branch_id' => $branch->id, 'name' => 'Meja '.$i, 'area' => $i <= 5 ? 'Dalam' : 'Teras', 'capacity' => 4, 'qr_token' => Str::random(40)]);
        }

        Supplier::create(['name' => 'Pemasok Sayur & Bumbu', 'phone' => '080000000001', 'lead_time_days' => 1]);
        Supplier::create(['name' => 'Pemasok Ayam', 'phone' => '080000000002', 'lead_time_days' => 1]);

        // Bahan baku: [nama, kategori, satuan dasar, HPP per satuan dasar, stok awal, min, konversi]
        $raw = [
            'beras' => ['Beras', 'Bahan pokok', 'gram', 14, 25000, 5000, ['kilogram' => 1000, 'karung' => 25000]],
            'ayam' => ['Ayam potong', 'Protein', 'potong', 6500, 80, 20, ['ekor' => 8]],
            'telur' => ['Telur ayam', 'Protein', 'butir', 2200, 120, 30, ['tray' => 30]],
            'minyak' => ['Minyak goreng', 'Bahan pokok', 'mililiter', 18, 10000, 2000, ['liter' => 1000, 'jeriken' => 18000]],
            'tepung' => ['Tepung bumbu', 'Bahan pokok', 'gram', 22, 5000, 1000, ['kilogram' => 1000]],
            'cabai' => ['Cabai rawit', 'Bumbu', 'gram', 60, 2000, 500, ['kilogram' => 1000]],
            'bawang' => ['Bawang putih', 'Bumbu', 'gram', 40, 1500, 300, ['kilogram' => 1000]],
            'garam' => ['Garam', 'Bumbu', 'gram', 8, 2000, 200, ['kilogram' => 1000]],
            'gula' => ['Gula pasir', 'Bahan pokok', 'gram', 17, 5000, 1000, ['kilogram' => 1000]],
            'teh' => ['Teh celup', 'Minuman', 'sachet', 300, 200, 50, ['box' => 25]],
            'jeruk' => ['Jeruk peras', 'Minuman', 'buah', 1500, 60, 20, ['kilogram' => 8]],
            'es' => ['Es batu', 'Minuman', 'gram', 1, 20000, 3000, ['karung' => 10000]],
            'kentang' => ['Kentang beku', 'Camilan', 'gram', 35, 5000, 1000, ['pack' => 1000]],
            'bubuk' => ['Bubuk keju', 'Bumbu', 'gram', 90, 500, 100, ['pack' => 250]],
            'kemasan' => ['Kemasan kertas', 'Kemasan', 'pcs', 700, 300, 50, ['pack' => 50]],
            'cup' => ['Gelas plastik', 'Kemasan', 'pcs', 500, 300, 50, ['pack' => 50]],
        ];
        $inv = app(InventoryService::class);
        $items = [];
        foreach ($raw as $key => [$name, $cat, $unit, $cost, $stock, $min, $conv]) {
            $item = InventoryItem::create(['name' => $name, 'category' => $cat, 'type' => 'raw', 'base_unit' => $unit, 'avg_cost' => $cost, 'min_stock' => $min]);
            foreach ($conv as $u => $f) {
                $item->conversions()->create(['unit' => $u, 'factor' => $f]);
            }
            $inv->post($item, $branch->id, 'opening', $stock, $cost, null, 'Saldo awal (data contoh)');
            $items[$key] = $item;
        }

        // Sub-recipe (bahan hasil produksi) — HPP dihitung dari resepnya / batch produksi
        $sambal = InventoryItem::create(['name' => 'Sambal geprek racikan', 'category' => 'Racikan', 'type' => 'sub_recipe', 'base_unit' => 'gram', 'min_stock' => 300]);
        $seasoning = InventoryItem::create(['name' => 'Seasoning keju racikan', 'category' => 'Racikan', 'type' => 'sub_recipe', 'base_unit' => 'gram', 'min_stock' => 100]);
        $nasi = InventoryItem::create(['name' => 'Nasi putih matang', 'category' => 'Racikan', 'type' => 'sub_recipe', 'base_unit' => 'gram', 'min_stock' => 2000]);

        $this->recipe('sub_recipe', 'Sambal geprek racikan', 1000, ['cabai' => 600, 'bawang' => 150, 'garam' => 20, 'minyak' => 250], $items, output: $sambal);
        $this->recipe('sub_recipe', 'Seasoning keju racikan', 500, ['bubuk' => 450, 'garam' => 30, 'gula' => 20], $items, output: $seasoning);
        $this->recipe('sub_recipe', 'Nasi putih matang', 2500, ['beras' => 1000], $items, output: $nasi);

        // Menu
        $catNasi = MenuCategory::create(['name' => 'Nasi & Lauk', 'sort' => 1]);
        $catMinum = MenuCategory::create(['name' => 'Minuman', 'sort' => 2]);
        $catCamilan = MenuCategory::create(['name' => 'Camilan', 'sort' => 3]);

        $items['sambal'] = $sambal;
        $items['seasoning'] = $seasoning;
        $items['nasi'] = $nasi;

        $menus = [
            ['Nasi Ayam Goreng', $catNasi, 18000, 'Dapur', 12, ['nasi' => 250, 'ayam' => 1, 'tepung' => 30, 'minyak' => 40, 'kemasan' => 1]],
            ['Nasi Ayam Geprek', $catNasi, 20000, 'Dapur', 12, ['nasi' => 250, 'ayam' => 1, 'tepung' => 30, 'minyak' => 40, 'sambal' => 40, 'kemasan' => 1]],
            ['Nasi Telur Balado', $catNasi, 13000, 'Dapur', 8, ['nasi' => 250, 'telur' => 2, 'sambal' => 25, 'minyak' => 20]],
            ['Kentang Crispy Keju', $catCamilan, 15000, 'Dapur', 7, ['kentang' => 150, 'minyak' => 50, 'seasoning' => 10, 'kemasan' => 1]],
            ['Es Teh Manis', $catMinum, 5000, 'Minuman', 3, ['teh' => 1, 'gula' => 20, 'es' => 150, 'cup' => 1]],
            ['Es Jeruk', $catMinum, 8000, 'Minuman', 4, ['jeruk' => 2, 'gula' => 20, 'es' => 150, 'cup' => 1]],
        ];
        foreach ($menus as [$name, $cat, $price, $station, $prep, $bom]) {
            $menu = Menu::create(['name' => $name, 'menu_category_id' => $cat->id, 'price' => $price, 'station_name' => $station, 'prep_minutes' => $prep]);
            $this->recipe('menu', $name, 1, $bom, $items, menu: $menu);
            if (str_contains($name, 'Geprek')) {
                MenuModifier::create(['menu_id' => $menu->id, 'group_name' => 'Level pedas', 'name' => 'Level 1', 'price' => 0]);
                MenuModifier::create(['menu_id' => $menu->id, 'group_name' => 'Level pedas', 'name' => 'Level 3', 'price' => 0]);
                MenuModifier::create(['menu_id' => $menu->id, 'group_name' => 'Tambahan', 'name' => 'Ekstra keju', 'price' => 4000]);
            }
            if (str_starts_with($name, 'Es')) {
                MenuModifier::create(['menu_id' => $menu->id, 'group_name' => 'Gula', 'name' => 'Kurang manis', 'price' => 0]);
            }
        }

        // Stok awal sub-recipe melalui batch produksi agar HPP tercatat
        auth()->login($userModels['owner']);
        $prod = app(\App\Domain\Production\ProductionService::class);
        foreach ([[$sambal, 2, 1950, 'Susut saat memasak'], [$seasoning, 1, 500, null], [$nasi, 4, 10000, null]] as [$item, $mult, $yield, $reason]) {
            $recipe = Recipe::where('output_item_id', $item->id)->first();
            $cons = $recipe->items->mapWithKeys(fn ($l) => [$l->inventory_item_id => $l->qty * $mult])->all();
            $prod->produce($recipe, $branch->id, $mult, $yield, $cons, $reason);
        }
        auth()->logout();
    }

    protected function recipe(string $type, string $name, float $yield, array $bom, array $items, ?InventoryItem $output = null, ?Menu $menu = null): Recipe
    {
        $recipe = Recipe::create([
            'type' => $type, 'name' => $name, 'yield_qty' => $yield, 'version' => 1, 'effective_from' => today(),
            'output_item_id' => $output?->id, 'menu_id' => $menu?->id,
        ]);
        foreach ($bom as $key => $qty) {
            $recipe->items()->create(['inventory_item_id' => $items[$key]->id, 'qty' => $qty]);
        }

        return $recipe;
    }
}
