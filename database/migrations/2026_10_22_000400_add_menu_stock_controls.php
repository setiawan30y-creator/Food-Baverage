<?php

/**
 * Kontrol stok menu: blokir penjualan saat bahan habis (per cabang)
 * & stok porsi harian opsional (per menu per cabang).
 * @2026-Alifiandra
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->boolean('block_out_of_stock')->default(true)->after('order_ahead_enabled');
        });
        Schema::table('branch_menu', function (Blueprint $table) {
            $table->unsignedInteger('daily_stock')->nullable()->after('sold_out');
        });
    }

    public function down(): void
    {
        Schema::table('branch_menu', fn (Blueprint $table) => $table->dropColumn('daily_stock'));
        Schema::table('branches', fn (Blueprint $table) => $table->dropColumn('block_out_of_stock'));
    }
};
