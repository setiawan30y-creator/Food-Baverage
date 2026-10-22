<?php

/**
 * ALMARA Restaurant & Beverage OS — Identity, Tenant & Branch.
 * @2026-Alifiandra
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('business_type')->default('warung_makan');
            $table->string('plan')->default('basic');
            $table->string('status')->default('active'); // trial, active, suspended
            $table->date('trial_ends_at')->nullable();
            $table->date('subscription_ends_at')->nullable();
            $table->unsignedInteger('max_branches')->default(1);
            $table->unsignedInteger('max_users')->default(10);
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20);
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('geofence_radius')->default(100); // meter
            $table->time('open_time')->default('08:00');
            $table->time('close_time')->default('21:00');
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('service_percent', 5, 2)->default(0);
            $table->boolean('order_ahead_enabled')->default(true);
            $table->unsignedInteger('pickup_slot_minutes')->default(15);
            $table->unsignedInteger('pickup_capacity_per_slot')->default(10);
            $table->boolean('weather_widget')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete(); // null = semua cabang
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone', 30)->nullable();
            $table->string('role', 30)->default('cashier');
            $table->string('theme_mode', 10)->nullable(); // preferensi pengguna: light/dark/system
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('company_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('legal_id')->nullable();
            $table->string('receipt_header')->nullable();
            $table->string('receipt_footer')->nullable();
            $table->timestamps();
        });

        Schema::create('theme_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mode', 10)->default('system');
            $table->string('preset', 30)->default('terracotta');
            $table->string('primary', 7)->default('#C2410C');
            $table->string('accent', 7)->default('#15803D');
            $table->string('background', 7)->default('#FAF7F2');
            $table->string('surface', 7)->default('#FFFFFF');
            $table->string('text', 7)->default('#1C1917');
            $table->string('border', 7)->default('#E7E0D6');
            $table->string('font', 60)->default('Plus Jakarta Sans');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 80);
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('reason')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'action']);
            $table->index(['auditable_type', 'auditable_id']);
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->default(0);
            $table->string('key', 30);
            $table->string('period', 10);
            $table->unsignedInteger('last_number')->default(0);
            $table->unique(['tenant_id', 'branch_id', 'key', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('theme_settings');
        Schema::dropIfExists('company_profiles');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('tenants');
    }
};
