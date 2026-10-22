<?php

namespace App\Domain\SaaS;

use App\Models\Branch;
use App\Models\CompanyProfile;
use App\Models\KitchenStation;
use App\Models\PlatformSetting;
use App\Models\ShiftTemplate;
use App\Models\Tenant;
use App\Models\ThemeSetting;
use App\Models\User;
use Database\Seeders\SnackMenuSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Membuat penyewa baru lengkap: tenant, cabang pertama, profil, tema,
 * stasiun dapur, kode shift, dan akun pemilik. Opsional: contoh menu snack.
 * @2026-Alifiandra
 */
class TenantProvisioner
{
    /**
     * @param  array{business_name:string, business_type?:string, owner_name:string, email:string, phone?:?string,
     *               password:string, plan?:string, branch_name?:?string, branch_address?:?string, status?:string}  $data
     * @return array{0: Tenant, 1: User}
     */
    public function create(array $data, bool $demoMenu = false): array
    {
        $result = DB::transaction(function () use ($data) {
            $plan = array_key_exists($data['plan'] ?? '', config('fnb.plans')) ? $data['plan'] : 'basic';
            $status = $data['status'] ?? 'trial';
            $tenant = new Tenant([
                'name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'business_type' => $data['business_type'] ?? 'warung_makan',
                'owner_name' => $data['owner_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'plan' => $plan,
                'status' => $status,
                'trial_ends_at' => today()->addDays((int) PlatformSetting::get('trial_days')),
                'subscription_ends_at' => $status === 'active' ? today()->addMonth() : null,
            ]);
            $tenant->applyPlanLimits();
            $tenant->save();
            tenancy()->setTenant($tenant);

            $branch = Branch::create([
                'name' => ($data['branch_name'] ?? null) ?: 'Cabang Utama',
                'code' => 'UTM',
                'address' => $data['branch_address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'open_time' => '08:00',
                'close_time' => '21:00',
            ]);
            tenancy()->setBranch($branch);

            CompanyProfile::create([
                'name' => $data['business_name'], 'address' => $data['branch_address'] ?? null,
                'phone' => $data['phone'] ?? null, 'whatsapp' => $data['phone'] ?? null, 'email' => $data['email'],
                'receipt_header' => 'Terima kasih atas kunjungan Anda', 'receipt_footer' => 'Simpan nota ini sebagai bukti pembayaran',
            ]);
            ThemeSetting::create([]);
            foreach ($this->stationsFor($data['business_type'] ?? '') as $name) {
                KitchenStation::create(['branch_id' => $branch->id, 'name' => $name]);
            }
            foreach ([
                ['P', 'Pagi', '07:00', '15:00', true, '#FDE68A'], ['S', 'Siang', '11:00', '19:00', true, '#BFDBFE'],
                ['M', 'Malam', '14:00', '22:00', true, '#C7D2FE'], ['OFF', 'Libur', null, null, false, '#E5E7EB'],
                ['C', 'Cuti', null, null, false, '#FBCFE8'], ['I', 'Izin', null, null, false, '#FED7AA'],
                ['L', 'Libur nasional', null, null, false, '#FECACA'],
            ] as [$code, $name, $start, $end, $working, $color]) {
                ShiftTemplate::create(compact('code', 'name') + ['start_time' => $start, 'end_time' => $end, 'is_working' => $working, 'color' => $color]);
            }

            $owner = User::create([
                'tenant_id' => $tenant->id, 'branch_id' => null, 'name' => $data['owner_name'], 'email' => $data['email'],
                'phone' => $data['phone'] ?? null, 'role' => 'owner', 'password' => $data['password'], 'is_active' => true,
            ]);

            return [$tenant, $owner];
        });

        if ($demoMenu) {
            tenancy()->setTenant($result[0]);
            app(SnackMenuSeeder::class)->run();
        }
        tenancy()->clear();

        return $result;
    }

    public function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'usaha';
        $base = Str::limit($base, 40, '');
        $reserved = ['admin', 'daftar', 'login', 'api', 'www', 'app'];
        $slug = $base;
        $i = 2;
        while (in_array($slug, $reserved, true) || Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    protected function stationsFor(string $type): array
    {
        return match ($type) {
            'kedai_minuman', 'coffee_shop' => ['Bar', 'Dapur'],
            'bakery' => ['Bakery', 'Minuman'],
            'snack' => ['Penggorengan', 'Minuman'],
            default => ['Dapur', 'Minuman'],
        };
    }
}
