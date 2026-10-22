<?php

namespace App\Http\Controllers;

/** @2026-Alifiandra */
abstract class Controller
{
    /** Pastikan data milik cabang aktif (isolasi cabang di backend). */
    protected function ensureBranch($model): void
    {
        abort_unless((int) $model->branch_id === (int) tenancy()->branchId(), 404);
    }

    protected function branch(): \App\Models\Branch
    {
        return tenancy()->branch();
    }

    protected function branchId(): int
    {
        return (int) tenancy()->branchId();
    }

    /** Ekspor CSV sederhana (dibuka di Excel). */
    protected function csv(string $filename, array $header, iterable $rows)
    {
        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
