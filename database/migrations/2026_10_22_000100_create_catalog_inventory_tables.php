<?php

/**
 * Menu & Pricing, Recipe/Sub-Recipe, Inventory & Purchasing.
 * @2026-Alifiandra
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kitchen_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_categories')->nullOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('sku', 40)->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->string('photo_path')->nullable();
            $table->string('station_name', 60)->default('Dapur'); // dirutekan ke stasiun bernama sama di cabang
            $table->unsignedInteger('prep_minutes')->default(10);
            $table->string('available_days', 20)->nullable(); // "1,2,3,4,5,6,7" (ISO)
            $table->time('available_from')->nullable();
            $table->time('available_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->string('group_name', 60); // Level pedas, Topping, Ukuran
            $table->string('name', 60);
            $table->decimal('price', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('branch_menu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 2)->nullable(); // null = pakai harga dasar
            $table->boolean('is_available')->default(true);
            $table->boolean('sold_out')->default(false);
            $table->timestamps();
            $table->unique(['branch_id', 'menu_id']);
        });

        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('old_price', 15, 2)->nullable();
            $table->decimal('new_price', 15, 2)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('sku', 40)->nullable();
            $table->string('category', 60)->nullable();
            $table->string('type', 20)->default('raw'); // raw = bahan beli/langsung pakai, sub_recipe = hasil produksi
            $table->string('base_unit', 20)->default('gram');
            $table->decimal('avg_cost', 18, 6)->default(0); // HPP per satuan dasar (weighted average)
            $table->decimal('min_stock', 15, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('item_unit_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('unit', 30);       // karton, kg, pack, botol
            $table->decimal('factor', 15, 4); // 1 unit = factor x satuan dasar
            $table->unique(['inventory_item_id', 'unit']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // opening, purchase, sale, production_in, production_out, waste, adjustment, opname, transfer_in, transfer_out
            $table->decimal('qty', 15, 4); // bertanda: + masuk, - keluar (satuan dasar)
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'branch_id', 'inventory_item_id']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('waste_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('reason', 30); // rusak, basi, kedaluwarsa, tumpah, gagal_produksi, lainnya
            $table->string('note')->nullable();
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->date('counted_at');
            $table->string('status', 20)->default('draft'); // draft, posted
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_qty', 15, 4);
            $table->decimal('counted_qty', 15, 4)->nullable();
            $table->decimal('difference', 15, 4)->default(0);
            $table->decimal('unit_cost', 18, 6)->default(0);
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->unsignedInteger('lead_time_days')->default(1);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->date('date');
            $table->string('status', 20)->default('draft'); // draft, received, cancelled
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->string('payment_status', 20)->default('unpaid'); // unpaid, partial, paid
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 15, 4);
            $table->string('unit', 30);
            $table->decimal('factor', 15, 4)->default(1);
            $table->decimal('base_qty', 15, 4);
            $table->decimal('price', 15, 2); // per unit input
            $table->decimal('subtotal', 15, 2);
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // menu, sub_recipe
            $table->foreignId('menu_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('output_item_id')->nullable()->constrained('inventory_items')->cascadeOnDelete();
            $table->string('name', 120);
            $table->decimal('yield_qty', 15, 4)->default(1); // menu: porsi; sub-recipe: satuan dasar hasil
            $table->decimal('expected_waste_percent', 5, 2)->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('recipe_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 15, 4); // satuan dasar bahan
        });

        Schema::create('production_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->decimal('multiplier', 10, 4)->default(1);
            $table->decimal('planned_yield', 15, 4);
            $table->decimal('actual_yield', 15, 4);
            $table->decimal('variance_qty', 15, 4)->default(0);
            $table->string('variance_reason')->nullable();
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('unit_cost', 18, 6)->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('produced_at');
            $table->timestamps();
        });

        Schema::create('production_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('qty', 15, 4);
            $table->decimal('unit_cost', 18, 6);
            $table->decimal('total_cost', 15, 2);
        });
    }

    public function down(): void
    {
        foreach (['production_consumptions', 'production_batches', 'recipe_items', 'recipes', 'purchase_items', 'purchases',
            'suppliers', 'stock_count_lines', 'stock_counts', 'waste_records', 'stock_movements', 'item_unit_conversions',
            'inventory_items', 'price_histories', 'branch_menu', 'menu_modifiers', 'menus', 'menu_categories', 'kitchen_stations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
