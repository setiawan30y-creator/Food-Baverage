<?php

namespace App\Domain\Shared;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Penomoran dokumen yang aman terhadap transaksi bersamaan
 * (row lock pada document_sequences).
 * @2026-Alifiandra
 */
class SequenceService
{
    public function next(int $tenantId, ?int $branchId, string $key, string $prefix, string $periodFormat = 'ymd', int $pad = 4): string
    {
        $branchId = $branchId ?? 0;
        $period = now()->format($periodFormat);

        $number = DB::transaction(function () use ($tenantId, $branchId, $key, $period) {
            $where = ['tenant_id' => $tenantId, 'branch_id' => $branchId, 'key' => $key, 'period' => $period];
            $row = DB::table('document_sequences')->where($where)->lockForUpdate()->first();
            if (! $row) {
                try {
                    DB::table('document_sequences')->insert($where + ['last_number' => 0]);
                } catch (QueryException $e) {
                    // dibuat oleh request lain secara bersamaan — lanjut kunci baris
                }
                $row = DB::table('document_sequences')->where($where)->lockForUpdate()->first();
            }
            $next = $row->last_number + 1;
            DB::table('document_sequences')->where('id', $row->id)->update(['last_number' => $next]);

            return $next;
        });

        return sprintf('%s-%s-%s', $prefix, $period, str_pad((string) $number, $pad, '0', STR_PAD_LEFT));
    }
}
