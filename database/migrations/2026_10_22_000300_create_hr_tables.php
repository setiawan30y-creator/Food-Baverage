<?php

/**
 * Employee, Shift, Attendance (GPS), Payroll.
 * @2026-Alifiandra
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // akun login opsional
            $table->string('code', 30);
            $table->string('name', 120);
            $table->string('phone', 30)->nullable();
            $table->string('position', 40); // pemilik, manajer, kasir, koki, ...
            $table->string('status', 20)->default('active'); // active, inactive, resigned
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('wage_method', 20)->default('monthly'); // monthly, daily, shift, hourly, commission, piece
            $table->decimal('wage_rate', 15, 2)->default(0);
            $table->decimal('allowance', 15, 2)->default(0); // tunjangan tetap per periode
            $table->decimal('overtime_rate', 15, 2)->default(0); // per jam lembur disetujui
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('code', 10); // P, S, M, OFF, C, I, L
            $table->string('name', 40);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_working')->default(true);
            $table->string('color', 7)->default('#E5E7EB');
            $table->timestamps();
            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('code', 10);
            $table->string('station', 40)->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });

        Schema::create('attendance_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // in, out
            $table->timestamp('occurred_at'); // waktu server
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy', 10, 2)->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            $table->boolean('within_geofence')->default(false);
            $table->string('flag', 30)->nullable(); // late, early_leave, outside_geofence, manual
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // izin, sakit, cuti, lembur
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('hours', 6, 2)->nullable(); // untuk lembur
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('submitted'); // draft, submitted, approved, rejected, cancelled
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 15, 2);
            $table->decimal('installment', 15, 2);
            $table->decimal('remaining', 15, 2);
            $table->string('note')->nullable();
            $table->string('status', 20)->default('active'); // active, settled
            $table->timestamps();
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 20)->default('draft'); // draft, calculated, approved, paid
            $table->decimal('total_net', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('wage_method', 20);
            $table->decimal('wage_rate', 15, 2);
            $table->decimal('units', 10, 2)->default(0); // hari / shift / jam
            $table->decimal('base_amount', 15, 2)->default(0);
            $table->decimal('allowance', 15, 2)->default(0);
            $table->decimal('overtime', 15, 2)->default(0);
            $table->decimal('bonus', 15, 2)->default(0);
            $table->decimal('deduction', 15, 2)->default(0); // BPJS/PPh21/potongan lain (diisi sesuai aturan berlaku)
            $table->decimal('advance_deduction', 15, 2)->default(0);
            $table->decimal('net', 15, 2)->default(0);
            $table->json('detail')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payroll_items', 'payroll_runs', 'employee_advances', 'leave_requests', 'attendance_events',
            'employee_schedules', 'shift_templates', 'employees'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
