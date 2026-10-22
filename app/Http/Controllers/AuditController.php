<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/** Log audit hanya-baca. @2026-Alifiandra */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        $logs = AuditLog::with(['user', 'branch'])
            ->when($request->action, fn ($q, $a) => $q->where('action', 'like', "{$a}%"))
            ->when($request->date, fn ($q, $d) => $q->whereDate('created_at', $d))
            ->latest('id')->paginate(50)->withQueryString();
        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');

        return view('audit.index', compact('logs', 'actions'));
    }
}
