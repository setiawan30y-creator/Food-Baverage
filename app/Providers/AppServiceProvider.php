<?php

namespace App\Providers;

use App\Support\Tenancy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/** @2026-Alifiandra */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Tenancy::class, fn () => new Tenancy);
    }

    public function boot(): void
    {
        Paginator::defaultView('partials.pagination');

        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(6)->by(strtolower((string) $r->input('email')).'|'.$r->ip()));
        RateLimiter::for('register', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('public-order', fn (Request $r) => Limit::perMinute(12)->by($r->ip()));
        RateLimiter::for('attendance', fn (Request $r) => Limit::perMinute(6)->by($r->user()?->id ?: $r->ip()));

        View::composer('*', function ($view) {
            $tenant = tenancy()->tenant();
            if ($tenant && ! $view->offsetExists('currentTenant')) {
                $view->with('currentTenant', $tenant)
                    ->with('currentBranch', tenancy()->branch())
                    ->with('companyProfile', $tenant->profile)
                    ->with('theme', $tenant->theme);
            }
        });
    }
}
