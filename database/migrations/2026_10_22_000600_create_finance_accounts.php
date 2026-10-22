<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekening & akun pembayaran (bank, QRIS, e-wallet, kas) + mutasi manual.
 * Pembayaran pesanan ditautkan ke rekening tujuan agar saldo per akun bisa dihitung.
 * @2026-Alifiandra
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete(); // null = dipakai semua cabang
            $table->string('name', 80);                // mis. "Bank BCA", "OVO", "QRIS"
            $table->string('method', 20);              // cash, qris, transfer, ewallet
            $table->string('account_number', 60)->nullable();
            $table->string('holder', 100)->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->date('opening_date');
            $table->boolean('is_active')->default(true);
            $table->string('notes', 200)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'method', 'is_active']);
        });

        Schema::create('account_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_account_id')->constrained()->cascadeOnDelete();
            $table->string('direction', 3);            // in / out
            $table->string('category', 30);            // deposit, withdrawal, fee, transfer, correction, other
            $table->decimal('amount', 15, 2);
            $table->date('date');
            $table->string('note', 200)->nullable();
            $table->string('transfer_group', 40)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['payment_account_id', 'date']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('payment_account_id')->nullable()->after('method')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_account_id');
        });
        Schema::dropIfExists('account_mutations');
        Schema::dropIfExists('payment_accounts');
    }
};
