<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Integrasi per usaha: payment gateway (Midtrans/Xendit) & WhatsApp gateway (Fonnte/Wablas).
 * Kredensial disimpan terenkripsi (APP_KEY).
 * @2026-Alifiandra
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                 // payment | whatsapp
            $table->string('provider', 30);             // midtrans | xendit | fonnte | wablas
            $table->boolean('is_active')->default(false);
            $table->text('credentials')->nullable();    // terenkripsi (cast encrypted:array)
            $table->json('settings')->nullable();
            $table->string('webhook_token', 64)->unique();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'type']);
        });

        Schema::create('gateway_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 30);
            $table->string('channel', 30);              // qris | va_bca | va_bni | va_bri | va_mandiri | va_permata
            $table->string('reference', 64)->unique();  // ID kita (order_id di gateway)
            $table->string('provider_ref', 100)->nullable();
            $table->decimal('amount', 15, 2);
            $table->decimal('fee', 15, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending, paid, expired, failed
            $table->text('qr_string')->nullable();
            $table->string('va_number', 60)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete(); // null = pesan platform (Super Admin)
            $table->string('provider', 30);
            $table->string('event', 40);
            $table->string('to', 30);
            $table->text('message');
            $table->string('status', 20)->default('queued'); // queued, sent, failed
            $table->string('error', 255)->nullable();
            $table->json('response')->nullable();
            $table->nullableMorphs('subject');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
        Schema::dropIfExists('gateway_charges');
        Schema::dropIfExists('tenant_integrations');
    }
};
