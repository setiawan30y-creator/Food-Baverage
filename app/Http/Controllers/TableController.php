<?php

namespace App\Http\Controllers;

use App\Domain\Ordering\OrderService;
use App\Domain\Shared\Audit;
use App\Models\DiningTable;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Peta meja, QR unik per meja, sesi meja, pindah meja. @2026-Alifiandra */
class TableController extends Controller
{
    public function index()
    {
        $tables = DiningTable::forBranch()->with(['activeSession.orders' => fn ($q) => $q->where('status', '!=', 'cancelled')])->orderBy('area')->orderBy('name')->get();

        return view('tables.index', compact('tables'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'area' => ['nullable', 'string', 'max:40'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
        DiningTable::create($data + ['branch_id' => $this->branchId(), 'qr_token' => Str::random(40)]);

        return back()->with('success', 'Meja ditambahkan.');
    }

    public function update(Request $request, DiningTable $table)
    {
        $this->ensureBranch($table);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'area' => ['nullable', 'string', 'max:40'],
            'capacity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
        $table->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Meja disimpan.');
    }

    /** Buat ulang QR (QR lama otomatis tidak berlaku). */
    public function regenerate(DiningTable $table)
    {
        $this->ensureBranch($table);
        $table->update(['qr_token' => Str::random(40)]);
        Audit::log('table.qr_regenerated', $table);

        return back()->with('success', 'QR meja '.$table->name.' dibuat ulang. Cetak dan ganti QR lama.');
    }

    public function close(DiningTable $table, OrderService $orders)
    {
        $this->ensureBranch($table);
        $orders->closeSession($table);

        return back()->with('success', 'Sesi meja '.$table->name.' ditutup.');
    }

    public function move(Request $request, DiningTable $table)
    {
        $this->ensureBranch($table);
        $data = $request->validate(['target_id' => ['required', 'integer', 'different:'.$table->id]]);
        $target = DiningTable::forBranch()->where('is_active', true)->findOrFail($data['target_id']);
        abort_if($target->activeSession, 422, 'Meja tujuan sedang terisi.');
        $session = $table->activeSession;
        abort_unless($session, 422, 'Meja asal tidak memiliki sesi aktif.');

        DB::transaction(function () use ($table, $target, $session) {
            $session->update(['dining_table_id' => $target->id]);
            Order::where('table_session_id', $session->id)->update(['dining_table_id' => $target->id]);
            $target->update(['status' => $table->status]);
            $table->update(['status' => 'available']);
        });
        Audit::log('table.moved', $session, ['table' => $table->name], ['table' => $target->name]);

        return back()->with('success', "Sesi dipindah dari {$table->name} ke {$target->name}.");
    }

    public function printQr()
    {
        $tables = DiningTable::forBranch()->where('is_active', true)->orderBy('name')->get();

        return view('tables.print', compact('tables'));
    }
}
