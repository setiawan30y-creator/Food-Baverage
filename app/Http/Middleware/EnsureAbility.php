<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pemeriksaan hak akses peran. Contoh: ->middleware('ability:pos'). @2026-Alifiandra */
class EnsureAbility
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $user = $request->user();
        foreach ($abilities as $ability) {
            if ($user && $user->hasAbility($ability)) {
                return $next($request);
            }
        }
        abort(403, 'Anda tidak memiliki akses ke fitur ini.');
    }
}
