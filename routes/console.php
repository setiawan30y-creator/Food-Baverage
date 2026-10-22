<?php

/**
 * Perintah konsol & jadwal ALMARA F&B OS. @2026-Alifiandra
 */

use App\Domain\SaaS\SubscriptionService;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('saas:check-subscriptions', function (SubscriptionService $subs) {
    $r = $subs->dailyCheck();
    $this->info("Status diperbarui: {$r['status_changed']} · tagihan perpanjangan dibuat: {$r['invoices_created']}");
})->purpose('Perbarui status langganan penyewa & buat tagihan perpanjangan');

Artisan::command('saas:superadmin {email} {--name=Super Admin} {--password=}', function (string $email) {
    $password = $this->option('password') ?: $this->secret('Kata sandi (min. 8 karakter)');
    if (strlen((string) $password) < 8) {
        $this->error('Kata sandi minimal 8 karakter.');

        return 1;
    }
    $user = User::where('email', $email)->first();
    if ($user && $user->tenant_id) {
        $this->error('Email ini sudah dipakai akun usaha. Gunakan email lain untuk Super Admin.');

        return 1;
    }
    User::updateOrCreate(['email' => $email], [
        'name' => $this->option('name'), 'role' => 'superadmin', 'tenant_id' => null, 'branch_id' => null,
        'password' => $password, 'is_active' => true,
    ]);
    $this->info("Super Admin {$email} siap. Login lalu buka /admin.");
})->purpose('Buat / perbarui akun Super Admin platform');

Schedule::command('saas:check-subscriptions')->dailyAt('00:30');
