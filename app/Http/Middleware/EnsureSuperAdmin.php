<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Panel /admin hanya untuk Super Admin (pemilik platform SaaS). @2026-Alifiandra */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403, 'Halaman ini khusus Super Admin.');
        tenancy()->clear();

        return $next($request);
    }
}
