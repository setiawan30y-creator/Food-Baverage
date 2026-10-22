<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Shared\Audit;
use App\Http\Controllers\Controller;
use App\Models\CompanyProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Profil perusahaan & logo. @2026-Alifiandra */
class CompanyController extends Controller
{
    public function edit()
    {
        $profile = CompanyProfile::firstOrCreate([], ['name' => tenancy()->tenant()->name]);

        return view('settings.company', compact('profile'));
    }

    public function update(Request $request)
    {
        $profile = CompanyProfile::firstOrCreate([], ['name' => tenancy()->tenant()->name]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'legal_id' => ['nullable', 'string', 'max:120'],
            'receipt_header' => ['nullable', 'string', 'max:255'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024', 'dimensions:min_width=64,min_height=64,max_width=2000,max_height=2000'],
        ]);
        $before = $profile->only(array_keys($data));

        if ($request->hasFile('logo')) {
            if ($profile->logo_path) {
                Storage::disk('local')->delete($profile->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('tenants/'.tenancy()->tenantId().'/logo', 'local');
        }
        unset($data['logo']);
        $profile->update($data);
        tenancy()->tenant()->update(['name' => $data['name']]);
        Audit::log('settings.company', $profile, $before, $profile->only(array_keys($before)));

        return back()->with('success', 'Profil perusahaan disimpan.');
    }

    public function deleteLogo()
    {
        $profile = CompanyProfile::firstOrFail();
        if ($profile->logo_path) {
            Storage::disk('local')->delete($profile->logo_path);
            $profile->update(['logo_path' => null]);
            Audit::log('settings.logo_deleted', $profile);
        }

        return back()->with('success', 'Logo dihapus.');
    }
}
