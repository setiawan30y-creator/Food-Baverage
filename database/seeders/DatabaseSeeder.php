<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\DiningTable;
use App\Models\Employee;
use App\Models\ShiftTemplate;
use App\Models\Tenant;
use App\Models\ThemeSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Data contoh kedai snack untuk mencoba aplikasi (menu: SnackMenuSeeder).
 * Semua nama bersifat generik/netral.
 * @2026-Alifiandra
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::create([
            'name' => 'Kedai Snack Contoh',
            'slug' => 'kedai-snack',
            'business_type' => 'snack',
            'plan' => 'pro',
            'status' => 'active',
            'max_branches' => 5,
            'max_users' => 50,
            'owner_name' => 'Pemilik Usaha',
            'email' => 'owner@contoh.test',
            'phone' => '080000000000',
            'subscription_ends_at' => today()->addYear(),
        ]);
        tenancy()->setTenant($tenant);

        $branch = Branch::create([
            'name' => 'Cabang Utama', 'code' => 'UTM', 'address' => 'Alamat cabang utama',
            'phone' => '080000000000', 'latitude' => -6.2000000, 'longitude' => 106.8166660, 'geofence_radius' => 150,
            'open_time' => '07:00', 'close_time' => '22:00', 'tax_percent' => 0, 'service_percent' => 0,
        ]);
        tenancy()->setBranch($branch);

        CompanyProfile::create([
            'name' => 'Kedai Snack Contoh', 'address' => 'Alamat usaha', 'phone' => '080000000000',
            'whatsapp' => '080000000000', 'email' => 'info@contoh.test',
            'receipt_header' => 'Terima kasih telah berkunjung', 'receipt_footer' => 'Simpan nota ini sebagai bukti pembayaran',
        ]);
        ThemeSetting::create([]);


        $users = [
            ['Pemilik Usaha', 'owner@contoh.test', 'owner', null],
            ['Manajer Outlet', 'manajer@contoh.test', 'manager', $branch->id],
            ['Kasir Satu', 'kasir@contoh.test', 'cashier', $branch->id],
            ['Juru Goreng', 'dapur@contoh.test', 'kitchen', $branch->id],
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
            ['K003', 'Juru Goreng', 'koki', 'shift', 130000, 'kitchen'],
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

        // Akun Super Admin platform (pemilik SaaS) — tidak terikat usaha mana pun
        User::create(['tenant_id' => null, 'branch_id' => null, 'name' => 'Super Admin', 'email' => 'superadmin@contoh.test', 'role' => 'superadmin', 'password' => 'password', 'is_active' => true]);

        // Katalog contoh: menu snack (kentang crispy, kentang spiral, corndog, dll.)
        auth()->login($userModels['owner']);
        $this->call(SnackMenuSeeder::class);
        auth()->logout();
    }
}
