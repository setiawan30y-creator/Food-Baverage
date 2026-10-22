<?php

namespace App\Domain\SaaS;

use App\Models\AuditLog;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Siklus langganan: buat tagihan, verifikasi pembayaran (memperpanjang masa aktif
 * & menerapkan paket), dan pemeriksaan harian status penyewa.
 * @2026-Alifiandra
 */
class SubscriptionService
{
    public function createInvoice(Tenant $tenant, string $plan, int $months, ?int $userId = null, ?string $note = null): SubscriptionInvoice
    {
        if (! array_key_exists($plan, config('fnb.plans')) || ! array_key_exists($months, config('fnb.billing_months'))) {
            throw new \DomainException('Paket atau durasi tidak valid.');
        }
        // Satu tagihan terbuka per penyewa: tagihan lama yang belum dibayar dibatalkan
        SubscriptionInvoice::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('status', 'unpaid')
            ->update(['status' => 'void', 'note' => 'Diganti tagihan baru']);

        return SubscriptionInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'plan' => $plan,
            'months' => $months,
            'amount' => SubscriptionInvoice::priceFor($plan, $months),
            'status' => 'unpaid',
            'due_date' => ($end = $tenant->accessEndsAt()) && $end->gt(today()->addDays(3)) ? $end : today()->addDays(3),
            'note' => $note,
            'created_by' => $userId,
        ]);
    }

    /** Tandai lunas → paket diterapkan & masa aktif diperpanjang dari tanggal akhir sekarang (bila masih berlaku). */
    public function markPaid(SubscriptionInvoice $invoice, int $adminId, ?string $method = null, ?string $ref = null): void
    {
        if (! $invoice->isOpen()) {
            throw new \DomainException('Tagihan ini sudah diproses.');
        }
        DB::transaction(function () use ($invoice, $adminId, $method, $ref) {
            $tenant = Tenant::lockForUpdate()->findOrFail($invoice->tenant_id);
            $before = $tenant->only(['plan', 'status', 'subscription_ends_at', 'max_branches', 'max_users']);
            $current = $tenant->subscription_ends_at;
            $start = ($current && $current->gte(today()) && $tenant->status !== 'trial') ? $current->copy()->addDay() : today();
            $end = $start->copy()->addMonthsNoOverflow($invoice->months)->subDay();

            $tenant->plan = $invoice->plan;
            $tenant->applyPlanLimits();
            $tenant->status = 'active';
            $tenant->suspended_reason = null;
            $tenant->subscription_ends_at = $end;
            $tenant->save();

            $invoice->update([
                'status' => 'paid', 'paid_at' => now(), 'verified_by' => $adminId,
                'payment_method' => $method ?: ($invoice->payment_method ?: 'transfer'), 'payment_ref' => $ref ?: $invoice->payment_ref,
                'period_start' => $start, 'period_end' => $end,
            ]);
            $this->audit($tenant->id, $adminId, 'saas.invoice_paid', $invoice, $before, $tenant->only(array_keys($before)));
        });
    }

    /** Pemeriksaan harian: simpan status efektif & buat tagihan perpanjangan menjelang jatuh tempo. */
    public function dailyCheck(): array
    {
        $changed = 0;
        $invoiced = 0;
        foreach (Tenant::all() as $tenant) {
            $effective = $tenant->effectiveStatus();
            $stored = $tenant->status;
            if ($stored !== 'suspended' && $effective !== $stored && ! ($stored === 'trial' && $effective === 'trial')) {
                $tenant->update(['status' => $effective]);
                $this->audit($tenant->id, null, 'saas.status_changed', $tenant, ['status' => $stored], ['status' => $effective]);
                $changed++;
            }
            $days = $tenant->daysLeft();
            $hasOpen = SubscriptionInvoice::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereIn('status', ['unpaid', 'waiting'])->exists();
            $threshold = $tenant->status === 'trial' ? 3 : 7;
            if (! $hasOpen && $days !== null && $days <= $threshold && $effective !== 'suspended') {
                $this->createInvoice($tenant, $tenant->plan, 1, null, 'Tagihan perpanjangan otomatis');
                $invoiced++;
            }
        }

        return ['status_changed' => $changed, 'invoices_created' => $invoiced];
    }

    public function audit(int $tenantId, ?int $userId, string $action, $subject = null, ?array $before = null, ?array $after = null, ?string $reason = null): void
    {
        AuditLog::withoutGlobalScopes()->create([
            'tenant_id' => $tenantId, 'user_id' => $userId, 'action' => $action,
            'auditable_type' => $subject ? class_basename($subject) : null, 'auditable_id' => $subject?->getKey(),
            'before' => $before, 'after' => $after, 'reason' => $reason, 'ip_address' => request()?->ip(),
        ]);
    }
}
