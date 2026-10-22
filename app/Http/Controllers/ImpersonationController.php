<?php

namespace App\Http\Controllers;

use App\Domain\SaaS\SubscriptionService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Kembali dari mode "Masuk sebagai" ke akun Super Admin. @2026-Alifiandra */
class ImpersonationController extends Controller
{
    public function stop(Request $request, SubscriptionService $subs)
    {
        $adminId = $request->session()->pull('impersonator_id');
        $admin = $adminId ? User::find($adminId) : null;
        abort_unless($admin && $admin->isSuperAdmin(), 403);
        $current = $request->user();
        if ($current?->tenant_id) {
            $subs->audit($current->tenant_id, $admin->id, 'saas.impersonate_stop', $current);
        }
        Auth::login($admin);
        $request->session()->regenerate();
        $request->session()->forget('branch_id');

        return redirect()->route('admin.tenants.show', $current?->tenant_id ?? 0);
    }
}
