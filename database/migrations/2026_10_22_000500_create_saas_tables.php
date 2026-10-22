<?php

/**
 * SaaS: data penyewa, tagihan langganan, pengaturan platform.
 * @2026-Alifiandra
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('owner_name', 120)->nullable()->after('business_type');
            $table->string('email')->nullable()->after('owner_name');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('suspended_reason')->nullable()->after('status');
            $table->text('notes')->nullable()->after('max_users');
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40)->nullable()->unique();
            $table->string('plan', 30);
            $table->unsignedInteger('months')->default(1);
            $table->decimal('amount', 15, 2);
            $table->string('status', 20)->default('unpaid'); // unpaid, waiting (bukti diunggah), paid, void
            $table->date('due_date');
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_ref', 100)->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamp('proof_uploaded_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('subscription_invoices');
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn(['owner_name', 'email', 'phone', 'suspended_reason', 'notes']));
    }
};
