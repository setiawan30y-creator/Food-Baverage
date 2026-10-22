<?php

/**
 * Ordering, Table & QR Session, POS & Payment, Cashier Closing.
 * @2026-Alifiandra
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('points')->default(0);
            $table->boolean('marketing_consent')->default(false);
            $table->timestamp('consent_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'phone']);
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->string('area', 40)->nullable();
            $table->unsignedInteger('capacity')->default(4);
            $table->string('qr_token', 64)->unique();
            $table->string('status', 20)->default('available'); // available, occupied, awaiting_payment
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dining_table_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('status', 20)->default('open'); // open, closed
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('number', 40);
            $table->string('channel', 20)->default('pos'); // pos, qr, order_ahead
            $table->string('type', 20)->default('dine_in'); // dine_in, takeaway, pickup
            $table->foreignId('dining_table_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('table_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name', 120)->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('status', 20)->default('new'); // new, accepted, preparing, ready, completed, cancelled
            $table->string('payment_status', 20)->default('unpaid'); // unpaid, pending, paid, refunded
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('service', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->decimal('cost_total', 15, 2)->default(0); // snapshot HPP
            $table->timestamp('pickup_at')->nullable();
            $table->string('pickup_code', 10)->nullable();
            $table->string('tracking_token', 64)->unique();
            $table->string('idempotency_key', 80)->nullable();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->boolean('stock_deducted')->default(false);
            $table->unsignedInteger('print_count')->default(0);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'branch_id', 'number']);
            $table->unique(['tenant_id', 'idempotency_key']);
            $table->index(['tenant_id', 'branch_id', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->unsignedInteger('qty');
            $table->decimal('price', 15, 2);
            $table->json('modifiers')->nullable();
            $table->decimal('modifiers_total', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('unit_cost', 18, 6)->default(0); // snapshot HPP per porsi
            $table->string('station_name', 60)->default('Dapur');
            $table->string('kitchen_status', 20)->default('new'); // new, preparing, ready, served, cancelled
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 20); // cash, qris, transfer, ewallet
            $table->decimal('amount', 15, 2);
            $table->decimal('received', 15, 2)->default(0);
            $table->decimal('change', 15, 2)->default(0);
            $table->decimal('provider_fee', 15, 2)->default(0);
            $table->string('status', 20)->default('pending'); // pending, success, failed, expired, refunded
            $table->string('reference', 100)->nullable();
            $table->string('provider', 40)->nullable();
            $table->string('idempotency_key', 80)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'idempotency_key']);
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // income, expense, deposit
            $table->string('category', 60)->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cash_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cashier_shift_id')->constrained()->cascadeOnDelete(); // bisa >1 bila closing dibuka ulang
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->decimal('opening_cash', 15, 2);
            $table->decimal('cash_sales', 15, 2);
            $table->decimal('other_income', 15, 2);
            $table->decimal('expenses', 15, 2);
            $table->decimal('deposits', 15, 2);
            $table->decimal('expected_cash', 15, 2);
            $table->string('count_method', 20)->default('total'); // total, denomination
            $table->decimal('counted_cash', 15, 2);
            $table->decimal('difference', 15, 2);
            $table->json('non_cash')->nullable(); // rekap per metode non-tunai
            $table->decimal('non_cash_total', 15, 2)->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('final'); // final, reopened
            $table->string('approval_status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('finalized_at');
            $table->timestamps();
        });

        Schema::create('cash_closing_denominations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_closing_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('denomination');
            $table->unsignedInteger('qty');
            $table->decimal('subtotal', 15, 2);
        });
    }

    public function down(): void
    {
        foreach (['cash_closing_denominations', 'cash_closings', 'cash_movements', 'payments', 'order_status_histories',
            'order_items', 'orders', 'cashier_shifts', 'table_sessions', 'dining_tables', 'customers'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
