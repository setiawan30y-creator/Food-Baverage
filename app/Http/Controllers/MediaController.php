<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Menu;
use Illuminate\Support\Facades\Storage;

/**
 * Menyajikan logo & foto menu dari disk privat (tidak lewat URL publik langsung).
 * Logo dan foto menu memang tampil untuk pelanggan, sehingga boleh diakses tanpa login.
 * @2026-Alifiandra
 */
class MediaController extends Controller
{
    public function logo(int $tenant)
    {
        $profile = CompanyProfile::withoutGlobalScopes()->where('tenant_id', $tenant)->firstOrFail();

        return $this->serve($profile->logo_path);
    }

    public function menu(int $menu)
    {
        $m = Menu::withoutGlobalScopes()->findOrFail($menu);

        return $this->serve($m->photo_path);
    }

    protected function serve(?string $path)
    {
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'public, max-age=86400']);
    }
}
