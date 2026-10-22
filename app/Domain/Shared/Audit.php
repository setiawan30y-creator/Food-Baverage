<?php

namespace App\Domain\Shared;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Jejak audit untuk tindakan sensitif. Log tidak dapat diubah dari UI.
 * @2026-Alifiandra
 */
class Audit
{
    public static function log(string $action, ?Model $subject = null, ?array $before = null, ?array $after = null, ?string $reason = null): void
    {
        $tenantId = tenancy()->tenantId() ?? ($subject->tenant_id ?? null);
        if (! $tenantId) {
            return;
        }

        AuditLog::create([
            'tenant_id' => $tenantId,
            'branch_id' => tenancy()->branchId() ?? ($subject->branch_id ?? null),
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $subject ? class_basename($subject) : null,
            'auditable_id' => $subject?->getKey(),
            'reason' => $reason,
            'before' => $before,
            'after' => $after,
            'ip_address' => request()?->ip(),
        ]);
    }
}
