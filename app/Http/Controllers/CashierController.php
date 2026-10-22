<?php

namespace App\Http\Controllers;

use App\Domain\CashClosing\ClosingService;
use App\Domain\Shared\Audit;
use App\Domain\Shared\SequenceService;
use App\Models\CashierShift;
use App\Models\CashMovement;
use Illuminate\Http\Request;

/** Shift kasir, kas masuk/keluar, form closing (total atau per pecahan). @2026-Alifiandra */
class CashierController extends Controller
{
    public function index(ClosingService $closing)
    {
        $shift = $this->currentShift();
        $summary = $shift ? $closing->summary($shift) : null;
        $movements = $shift ? $shift->cashMovements()->with('user')->latest()->get() : collect();

        return view('cashier.index', compact('shift', 'summary', 'movements'));
    }

    public function open(Request $request, SequenceService $seq)
    {
        abort_if($this->currentShift(), 422, 'Shift Anda masih terbuka.');
        $data = $request->validate(['opening_cash' => ['required', 'numeric', 'min:0']]);
        $shift = CashierShift::create([
            'branch_id' => $this->branchId(),
            'user_id' => auth()->id(),
            'number' => $seq->next(tenancy()->tenantId(), $this->branchId(), 'shift', 'SHF'),
            'opening_cash' => $data['opening_cash'],
            'opened_at' => now(),
            'status' => 'open',
        ]);
        Audit::log('cashier.shift_opened', $shift, null, ['opening_cash' => $data['opening_cash']]);

        return back()->with('success', 'Shift kasir dibuka.');
    }

    public function movement(Request $request)
    {
        $shift = $this->currentShift();
        abort_unless($shift, 422, 'Buka shift dahulu.');
        $data = $request->validate([
            'type' => ['required', 'in:income,expense,deposit'],
            'category' => ['nullable', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['required', 'string', 'max:200'],
        ]);
        $m = CashMovement::create($data + ['branch_id' => $this->branchId(), 'cashier_shift_id' => $shift->id, 'user_id' => auth()->id()]);
        Audit::log('cashier.'.$data['type'], $m, null, ['amount' => $data['amount']], $data['note']);

        return back()->with('success', 'Kas tercatat.');
    }

    public function closeForm(ClosingService $closing)
    {
        $shift = $this->currentShift();
        abort_unless($shift, 404);

        return view('cashier.close', ['shift' => $shift, 'summary' => $closing->summary($shift), 'denominations' => config('fnb.denominations')]);
    }

    public function close(Request $request, ClosingService $closing)
    {
        $shift = $this->currentShift();
        abort_unless($shift, 404);
        $data = $request->validate([
            'count_method' => ['required', 'in:total,denomination'],
            'counted_total' => ['nullable', 'required_if:count_method,total', 'numeric', 'min:0'],
            'denominations' => ['nullable', 'array'],
            'denominations.*' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $denoms = collect($data['denominations'] ?? [])->only(config('fnb.denominations'))->all();
        $result = $closing->finalize($shift, $data['count_method'], (float) ($data['counted_total'] ?? 0), $denoms, $data['reason'] ?? null);

        return redirect()->route('closings.show', $result)->with('success', 'Closing '.$result->number.' tersimpan.');
    }

    protected function currentShift(): ?CashierShift
    {
        return CashierShift::forBranch()->where('user_id', auth()->id())->where('status', 'open')->latest()->first();
    }
}
