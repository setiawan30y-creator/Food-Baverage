<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menetapkan tenant & cabang aktif dari pengguna yang login.
 * Akses cabang diberlakukan di backend.
 * @2026-Alifiandra
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->is_active || ! $user->tenant) {
            Auth::logout();

            return redirect()->route('login')->withErrors(['email' => 'Akun tidak aktif atau tidak terhubung ke usaha mana pun.']);
        }
        if (! $user->tenant->isUsable()) {
            Auth::logout();

            return redirect()->route('login')->withErrors(['email' => 'Langganan usaha ini tidak aktif. Hubungi penyedia layanan.']);
        }

        tenancy()->setTenant($user->tenant);

        $branchId = (int) $request->session()->get('branch_id', $user->branch_id ?? 0);
        $branch = $branchId ? Branch::where('is_active', true)->find($branchId) : null;
        if (! $branch || ! $user->canAccessBranch($branch->id)) {
            $branch = $user->branch_id
                ? Branch::where('is_active', true)->find($user->branch_id)
                : Branch::where('is_active', true)->orderBy('id')->first();
        }
        if (! $branch) {
            abort(403, 'Tidak ada cabang aktif yang dapat diakses.');
        }
        $request->session()->put('branch_id', $branch->id);
        tenancy()->setBranch($branch);

        return $next($request);
    }
}
