<?php

namespace App\Http\Controllers;

use App\Domain\SaaS\SubscriptionService;
use App\Models\Branch;
use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** Halaman Langganan untuk pemilik usaha. @2026-Alifiandra */
class SubscriptionController extends Controller
{
    public function index()
    {
        $tenant = tenancy()->tenant();

        return view('subscription.index', [
            'tenant' => $tenant,
            'plans' => config('fnb.plans'),
            'invoices' => SubscriptionInvoice::latest('id')->get(),
            'open' => SubscriptionInvoice::whereIn('status', ['unpaid', 'waiting'])->latest('id')->first(),
            'usage' => ['branches' => Branch::count(), 'users' => User::where('tenant_id', $tenant->id)->count()],
        ]);
    }

    public function store(Request $request, SubscriptionService $subs)
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys(config('fnb.plans')))],
            'months' => ['required', Rule::in(array_keys(config('fnb.billing_months')))],
        ]);
        $tenant = tenancy()->tenant();
        $plan = config("fnb.plans.{$data['plan']}");
        if (Branch::count() > $plan['max_branches'] || User::where('tenant_id', $tenant->id)->count() > $plan['max_users']) {
            return back()->with('error', "Paket {$plan['label']} hanya untuk {$plan['max_branches']} cabang & {$plan['max_users']} pengguna. Kurangi cabang/pengguna atau pilih paket lebih tinggi.");
        }
        $invoice = $subs->createInvoice($tenant, $data['plan'], (int) $data['months'], auth()->id());

        return redirect()->route('subscription.invoice', $invoice)->with('success', 'Tagihan dibuat. Silakan transfer lalu unggah bukti pembayaran.');
    }

    public function show(SubscriptionInvoice $invoice)
    {
        return view('subscription.invoice', [
            'invoice' => $invoice,
            'bank' => ['name' => PlatformSetting::get('bank_name'), 'account' => PlatformSetting::get('bank_account'), 'holder' => PlatformSetting::get('bank_holder')],
            'support' => PlatformSetting::get('support_whatsapp'),
        ]);
    }

    public function uploadProof(Request $request, SubscriptionInvoice $invoice)
    {
        abort_unless($invoice->isOpen(), 422, 'Tagihan ini sudah diproses.');
        $data = $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'payment_ref' => ['nullable', 'string', 'max:100'],
        ]);
        if ($invoice->proof_path) {
            Storage::disk('local')->delete($invoice->proof_path);
        }
        $invoice->update([
            'proof_path' => $request->file('proof')->store('tenants/'.$invoice->tenant_id.'/billing', 'local'),
            'proof_uploaded_at' => now(),
            'payment_ref' => $data['payment_ref'] ?? null,
            'payment_method' => 'transfer',
            'status' => 'waiting',
        ]);

        return back()->with('success', 'Bukti pembayaran terkirim. Langganan aktif setelah diverifikasi admin.');
    }

    public function proof(SubscriptionInvoice $invoice)
    {
        abort_unless($invoice->proof_path && Storage::disk('local')->exists($invoice->proof_path), 404);

        return response()->file(Storage::disk('local')->path($invoice->proof_path));
    }
}
