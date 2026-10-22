<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Tenant;
use App\Models\ThemeSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PWA per penyewa: manifest dinamis (nama, warna, ikon dari logo usaha)
 * untuk aplikasi staf (/) dan aplikasi pelanggan Order Ahead (/o/{slug}).
 * @2026-Alifiandra
 */
class PwaController extends Controller
{
    /** Aplikasi staf: kasir, dapur, absen, dsb. */
    public function manifest()
    {
        $user = auth()->user();
        $tenant = $user?->tenant;
        $profile = $tenant ? CompanyProfile::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first() : null;
        $name = $profile?->name ?? $tenant?->name;

        $shortcuts = [];
        if ($user && $tenant) {
            foreach ([['pos', 'Kasir', '/pos'], ['kitchen', 'Dapur', '/kitchen'], ['attendance.self', 'Absen', '/attendance/me'], ['orders', 'Pesanan', '/orders']] as [$ability, $label, $url]) {
                if ($user->hasAbility($ability)) {
                    $shortcuts[] = ['name' => $label, 'short_name' => $label, 'url' => $url, 'icons' => [['src' => $this->iconUrl($tenant, $profile, 192), 'sizes' => '192x192', 'type' => 'image/png']]];
                }
            }
        }

        return $this->respond([
            'id' => '/?app=staf',
            'name' => $name ? $name.' · F&B OS' : config('fnb.product_name'),
            'short_name' => $name ? Str::limit($name, 12, '') : 'ALMARA F&B',
            'description' => 'Kasir, dapur, stok, resep/HPP, closing, karyawan & absensi.',
            'start_url' => '/',
            'scope' => '/',
        ], $tenant, $profile, $shortcuts);
    }

    /** Aplikasi pelanggan: pesan dari rumah & ambil di outlet. */
    public function customer(string $slug)
    {
        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $profile = CompanyProfile::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first();
        $name = $profile?->name ?? $tenant->name;

        return $this->respond([
            'id' => '/o/'.$tenant->slug,
            'name' => $name,
            'short_name' => Str::limit($name, 12, ''),
            'description' => 'Pesan dari rumah, ambil di outlet '.$name.'.',
            'start_url' => '/o/'.$tenant->slug,
            'scope' => '/o/'.$tenant->slug,
        ], $tenant, $profile, []);
    }

    /** Ikon PNG persegi dari logo usaha (dengan area aman untuk ikon "maskable"). */
    public function icon(int $tenant, int $size)
    {
        abort_unless(in_array($size, [192, 512], true), 404);
        $profile = CompanyProfile::withoutGlobalScopes()->where('tenant_id', $tenant)->first();
        $disk = Storage::disk('local');
        if (! $profile?->logo_path || ! $disk->exists($profile->logo_path) || ! function_exists('imagecreatefromstring')) {
            return redirect(asset("icons/icon-{$size}.png"));
        }
        $dir = "tenants/{$tenant}/pwa";
        $path = "{$dir}/icon-{$size}-".substr(md5($profile->logo_path.$profile->updated_at), 0, 10).'.png';
        if (! $disk->exists($path)) {
            $src = @imagecreatefromstring($disk->get($profile->logo_path));
            if (! $src) {
                return redirect(asset("icons/icon-{$size}.png"));
            }
            $theme = ThemeSetting::withoutGlobalScopes()->where('tenant_id', $tenant)->first();
            [$r, $g, $b] = sscanf($theme?->surface ?? '#FFFFFF', '#%02x%02x%02x');
            $canvas = imagecreatetruecolor($size, $size);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, $r, $g, $b));
            $pad = (int) round($size * 0.16);
            $box = $size - 2 * $pad;
            $w = imagesx($src);
            $h = imagesy($src);
            $scale = min($box / $w, $box / $h);
            $nw = (int) round($w * $scale);
            $nh = (int) round($h * $scale);
            imagealphablending($canvas, true);
            imagecopyresampled($canvas, $src, (int) (($size - $nw) / 2), (int) (($size - $nh) / 2), 0, 0, $nw, $nh, $w, $h);
            ob_start();
            imagepng($canvas, null, 6);
            foreach ($disk->files($dir) as $old) {
                if (str_contains($old, "icon-{$size}-")) {
                    $disk->delete($old);
                }
            }
            $disk->put($path, ob_get_clean());
        }

        return response()->file($disk->path($path), ['Content-Type' => 'image/png', 'Cache-Control' => 'public, max-age=86400']);
    }

    protected function iconUrl(?Tenant $tenant, ?CompanyProfile $profile, int $size): string
    {
        return $tenant && $profile?->logo_path ? route('pwa.icon', [$tenant->id, $size]).'?v='.$profile->updated_at?->timestamp : asset("icons/icon-{$size}.png");
    }

    protected function respond(array $base, ?Tenant $tenant, ?CompanyProfile $profile, array $shortcuts)
    {
        $theme = $tenant ? ThemeSetting::withoutGlobalScopes()->where('tenant_id', $tenant->id)->first() : null;
        $custom = $tenant && $profile?->logo_path;
        $icons = $custom
            ? [
                ['src' => $this->iconUrl($tenant, $profile, 192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $this->iconUrl($tenant, $profile, 512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $this->iconUrl($tenant, $profile, 512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ]
            : [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ];

        $manifest = $base + [
            'display' => 'standalone',
            'display_override' => ['standalone', 'minimal-ui'],
            'orientation' => 'any',
            'lang' => 'id',
            'dir' => 'ltr',
            'categories' => ['business', 'food'],
            'background_color' => $theme->background ?? '#FAF7F2',
            'theme_color' => $theme->primary ?? '#C2410C',
            'icons' => $icons,
        ];
        if ($shortcuts) {
            $manifest['shortcuts'] = $shortcuts;
        }

        return response()->json($manifest, 200, ['Content-Type' => 'application/manifest+json', 'Cache-Control' => 'no-cache'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
