<?php

namespace App\Http\Controllers\Auth;

use App\Domain\SaaS\TenantProvisioner;
use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** Pendaftaran mandiri penyewa baru (trial). @2026-Alifiandra */
class RegisterController extends Controller
{
    const TYPES = [
        'warung_makan' => 'Warung makan / warung nasi', 'snack' => 'Snack, kentang & street food', 'kedai_minuman' => 'Kedai minuman / jus',
        'coffee_shop' => 'Coffee shop', 'bakery' => 'Bakery & pastry', 'bakso_mie' => 'Bakso / mie', 'restoran' => 'Restoran', 'lainnya' => 'Lainnya',
    ];

    public function show(Request $request)
    {
        abort_unless(PlatformSetting::get('registration_open') === '1', 404);

        return view('auth.register', [
            'types' => self::TYPES,
            'plans' => config('fnb.plans'),
            'plan' => $request->query('paket', 'pro'),
            'trialDays' => (int) PlatformSetting::get('trial_days'),
        ]);
    }

    public function store(Request $request, TenantProvisioner $provisioner)
    {
        abort_unless(PlatformSetting::get('registration_open') === '1', 404);
        if (filled($request->input('website'))) { // honeypot anti-bot
            return redirect()->route('login');
        }
        $data = $request->validate([
            'business_name' => ['required', 'string', 'min:3', 'max:120'],
            'business_type' => ['required', Rule::in(array_keys(self::TYPES))],
            'owner_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'phone' => ['required', 'string', 'min:8', 'max:20'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'plan' => ['required', Rule::in(array_keys(config('fnb.plans')))],
            'branch_name' => ['nullable', 'string', 'max:120'],
            'branch_address' => ['nullable', 'string', 'max:255'],
            'agree' => ['accepted'],
        ], ['agree.accepted' => 'Anda perlu menyetujui syarat layanan.']);

        [$tenant, $owner] = $provisioner->create($data, $request->boolean('demo_menu'));

        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', "Selamat datang! Usaha {$tenant->name} siap dipakai. Masa trial {$tenant->daysLeft()} hari.");
    }
}
