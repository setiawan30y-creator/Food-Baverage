<?php

namespace App\Http\Controllers\Admin;

use App\Domain\SaaS\SubscriptionService;
use App\Domain\SaaS\TenantProvisioner;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\SubscriptionInvoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Panel Super Admin (pemilik platform SaaS): penyewa, tagihan, pengaturan,
 * dan "Masuk sebagai" pemilik usaha untuk dukungan (teraudit).
 * @2026-Alifiandra
 */
class AdminController extends Controller
{
    public function dashboard()
    {
        $tenants = Tenant::all();
        $byStatus = $tenants->groupBy(fn ($t) => $t->effectiveStatus())->map->count();
        $mrr = $tenants->filter(fn ($t) => in_array($t->effectiveStatus(), ['active', 'past_due'], true))
            ->sum(fn ($t) => (float) ($t->planConfig()['price'] ?? 0));
        $waiting = SubscriptionInvoice::withoutGlobalScopes()->where('status', 'waiting')->with('tenantRecord')->latest('proof_uploaded_at')->get();
        $endingSoon = $tenants->filter(fn ($t) => $t->isUsable() && $t->daysLeft() !== null && $t->daysLeft() <= 7)->sortBy(fn ($t) => $t->daysLeft());
        $newest = Tenant::latest('id')->limit(6)->get();
        $revenue = SubscriptionInvoice::withoutGlobalScopes()->where('status', 'paid')->whereMonth('paid_at', now()->month)->whereYear('paid_at', now()->year)->sum('amount');

        return view('admin.dashboard', compact('tenants', 'byStatus', 'mrr', 'waiting', 'endingSoon', 'newest', 'revenue'));
    }

    // ---------- Penyewa ----------
    public function tenants(Request $request)
    {
        $tenants = Tenant::withCount(['branches' => fn ($q) => $q->withoutGlobalScopes(), 'users'])
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('slug', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($request->plan, fn ($q, $p) => $q->where('plan', $p))
            ->latest('id')->get()
            ->when($request->status, fn ($c, $s) => $c->filter(fn ($t) => $t->effectiveStatus() === $s));

        return view('admin.tenants', ['tenants' => $tenants, 'plans' => config('fnb.plans')]);
    }

    public function createTenant()
    {
        return view('admin.tenant-create', ['types' => RegisterController::TYPES, 'plans' => config('fnb.plans')]);
    }

    public function storeTenant(Request $request, TenantProvisioner $provisioner, SubscriptionService $subs)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'min:3', 'max:120'],
            'business_type' => ['required', Rule::in(array_keys(RegisterController::TYPES))],
            'owner_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:120', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', Password::min(8)],
            'plan' => ['required', Rule::in(array_keys(config('fnb.plans')))],
            'status' => ['required', 'in:trial,active'],
            'branch_name' => ['nullable', 'string', 'max:120'],
            'branch_address' => ['nullable', 'string', 'max:255'],
        ]);
        [$tenant] = $provisioner->create($data, $request->boolean('demo_menu'));
        $subs->audit($tenant->id, auth()->id(), 'saas.tenant_created', $tenant, null, ['plan' => $tenant->plan, 'status' => $tenant->status]);

        return redirect()->route('admin.tenants.show', $tenant)->with('success', "Penyewa {$tenant->name} dibuat. Kirim email & kata sandi ke pemiliknya.");
    }

    public function showTenant(Tenant $tenant)
    {
        $branches = Branch::withoutGlobalScopes()->where('tenant_id', $tenant->id)->get();
        $users = User::where('tenant_id', $tenant->id)->orderBy('role')->get();
        $invoices = SubscriptionInvoice::withoutGlobalScopes()->where('tenant_id', $tenant->id)->latest('id')->get();
        $logs = AuditLog::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('action', 'like', 'saas.%')->with('user')->latest('id')->limit(20)->get();
        $stats = [
            'orders_30d' => Order::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('created_at', '>=', now()->subDays(30))->count(),
            'last_login' => User::where('tenant_id', $tenant->id)->max('last_login_at'),
        ];

        return view('admin.tenant-show', compact('tenant', 'branches', 'users', 'invoices', 'logs', 'stats') + ['plans' => config('fnb.plans')]);
    }

    public function updateTenant(Request $request, Tenant $tenant, SubscriptionService $subs)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'owner_name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30'],
            'plan' => ['required', Rule::in(array_keys(config('fnb.plans')))],
            'max_branches' => ['required', 'integer', 'min:1', 'max:1000'],
            'max_users' => ['required', 'integer', 'min:1', 'max:10000'],
            'trial_ends_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $before = $tenant->only(array_keys($data));
        $tenant->fill($data);
        if ($tenant->status !== 'suspended') {
            $tenant->status = $tenant->subscription_ends_at ? 'active' : 'trial';
            $tenant->status = $tenant->effectiveStatus();
        }
        $tenant->save();
        $subs->audit($tenant->id, auth()->id(), 'saas.tenant_updated', $tenant, $this->plain($before), $this->plain($tenant->only(array_keys($data))));

        return back()->with('success', 'Data penyewa disimpan.');
    }

    public function suspend(Request $request, Tenant $tenant, SubscriptionService $subs)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        $tenant->update(['status' => 'suspended', 'suspended_reason' => $data['reason']]);
        $subs->audit($tenant->id, auth()->id(), 'saas.tenant_suspended', $tenant, null, null, $data['reason']);

        return back()->with('success', 'Penyewa ditangguhkan. Data tetap tersimpan.');
    }

    public function activate(Tenant $tenant, SubscriptionService $subs)
    {
        $tenant->status = $tenant->subscription_ends_at ? 'active' : 'trial';
        $tenant->suspended_reason = null;
        $tenant->status = $tenant->effectiveStatus();
        $tenant->save();
        $subs->audit($tenant->id, auth()->id(), 'saas.tenant_activated', $tenant, null, ['status' => $tenant->status]);

        return back()->with('success', 'Penyewa diaktifkan kembali (status: '.$tenant->statusLabel().').');
    }

    public function impersonate(Request $request, Tenant $tenant, SubscriptionService $subs)
    {
        $owner = User::where('tenant_id', $tenant->id)->where('role', 'owner')->where('is_active', true)->orderBy('id')->first();
        abort_unless($owner, 422, 'Penyewa ini belum punya akun pemilik yang aktif.');
        $adminId = auth()->id();
        $subs->audit($tenant->id, $adminId, 'saas.impersonate_start', $owner, null, ['as' => $owner->email]);
        Auth::login($owner);
        $request->session()->regenerate();
        $request->session()->put('impersonator_id', $adminId);
        $request->session()->forget('branch_id');

        return redirect()->route('dashboard')->with('success', "Mode dukungan: Anda masuk sebagai {$owner->name} ({$tenant->name}).");
    }

    // ---------- Tagihan ----------
    public function invoices(Request $request)
    {
        $invoices = SubscriptionInvoice::withoutGlobalScopes()->with(['tenantRecord', 'verifier'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s), fn ($q) => $q->whereIn('status', ['waiting', 'unpaid', 'paid']))
            ->orderByRaw("CASE status WHEN 'waiting' THEN 0 WHEN 'unpaid' THEN 1 ELSE 2 END")->latest('id')->paginate(30)->withQueryString();

        return view('admin.invoices', compact('invoices'));
    }

    public function createInvoice(Request $request, Tenant $tenant, SubscriptionService $subs)
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys(config('fnb.plans')))],
            'months' => ['required', Rule::in(array_keys(config('fnb.billing_months')))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $invoice = $subs->createInvoice($tenant, $data['plan'], (int) $data['months'], auth()->id(), $data['note'] ?? null);
        $subs->audit($tenant->id, auth()->id(), 'saas.invoice_created', $invoice, null, ['amount' => $invoice->amount]);

        return back()->with('success', "Tagihan {$invoice->number} dibuat.");
    }

    public function markPaid(Request $request, int $invoice, SubscriptionService $subs)
    {
        $inv = SubscriptionInvoice::withoutGlobalScopes()->findOrFail($invoice);
        $data = $request->validate(['payment_method' => ['nullable', 'string', 'max:40'], 'payment_ref' => ['nullable', 'string', 'max:100']]);
        $subs->markPaid($inv, auth()->id(), $data['payment_method'] ?? null, $data['payment_ref'] ?? null);
        $tenant = Tenant::find($inv->tenant_id);

        return back()->with('success', "Pembayaran {$inv->number} diverifikasi. {$tenant->name} aktif s/d ".$tenant->subscription_ends_at->translatedFormat('d M Y').'.');
    }

    public function voidInvoice(Request $request, int $invoice, SubscriptionService $subs)
    {
        $inv = SubscriptionInvoice::withoutGlobalScopes()->findOrFail($invoice);
        abort_unless($inv->isOpen(), 422, 'Tagihan ini sudah diproses.');
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $inv->update(['status' => 'void', 'note' => $data['reason']]);
        $subs->audit($inv->tenant_id, auth()->id(), 'saas.invoice_void', $inv, null, null, $data['reason']);

        return back()->with('success', 'Tagihan dibatalkan.');
    }

    public function invoiceProof(int $invoice)
    {
        $inv = SubscriptionInvoice::withoutGlobalScopes()->findOrFail($invoice);
        abort_unless($inv->proof_path && Storage::disk('local')->exists($inv->proof_path), 404);

        return response()->file(Storage::disk('local')->path($inv->proof_path));
    }

    // ---------- Pengaturan platform ----------
    public function settings()
    {
        $keys = array_keys(config('fnb.platform_defaults'));

        return view('admin.settings', [
            'values' => collect($keys)->mapWithKeys(fn ($k) => [$k => PlatformSetting::get($k)]), 'plans' => config('fnb.plans'),
            'waTokenSet' => (bool) PlatformSetting::get('wa_token'),
            'waMessages' => \App\Models\WaMessage::whereNull('tenant_id')->latest()->limit(15)->get(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:60'],
            'bank_name' => ['required', 'string', 'max:60'],
            'bank_account' => ['required', 'string', 'max:40'],
            'bank_holder' => ['required', 'string', 'max:120'],
            'support_whatsapp' => ['required', 'string', 'max:30'],
        ]);
        $data['registration_open'] = $request->boolean('registration_open') ? '1' : '0';
        PlatformSetting::put($data);

        return back()->with('success', 'Pengaturan platform disimpan.');
    }

    /** WhatsApp platform: pengingat & konfirmasi tagihan langganan ke pemilik usaha. */
    public function updateWhatsapp(Request $request)
    {
        $data = $request->validate([
            'wa_provider' => ['required', Rule::in(array_keys(config('fnb.whatsapp_providers')))],
            'wa_base_url' => ['nullable', 'url', 'max:120'],
            'wa_token' => ['nullable', 'string', 'max:255'],
            'wa_secret_key' => ['nullable', 'string', 'max:255'],
            'wa_template_invoice_created' => ['nullable', 'string', 'max:1000'],
            'wa_template_invoice_paid' => ['nullable', 'string', 'max:1000'],
        ]);
        $values = [
            'wa_enabled' => $request->boolean('wa_enabled') ? '1' : '0',
            'wa_provider' => $data['wa_provider'],
            'wa_base_url' => $data['wa_base_url'] ?? '',
            'wa_event_invoice_created' => $request->boolean('wa_event_invoice_created') ? '1' : '0',
            'wa_event_invoice_paid' => $request->boolean('wa_event_invoice_paid') ? '1' : '0',
        ];
        foreach (['invoice_created', 'invoice_paid'] as $e) {
            $tpl = trim((string) ($data["wa_template_{$e}"] ?? ''));
            $values["wa_template_{$e}"] = $tpl === config("fnb.wa_platform_events.{$e}.template") ? '' : $tpl;
        }
        foreach (['wa_token', 'wa_secret_key'] as $k) {
            if (trim((string) ($data[$k] ?? '')) !== '') {
                $values[$k] = \Illuminate\Support\Facades\Crypt::encryptString(trim($data[$k]));
            }
        }
        PlatformSetting::put($values);

        return back()->with('success', 'Pengaturan WhatsApp platform disimpan.');
    }

    public function testWhatsapp(Request $request, \App\Domain\WhatsApp\WhatsAppService $wa)
    {
        $data = $request->validate(['phone' => ['nullable', 'string', 'max:30']]);
        $sender = new \App\Domain\WhatsApp\WhatsAppSender([
            'provider' => PlatformSetting::get('wa_provider'), 'token' => $wa::decrypt(PlatformSetting::get('wa_token')),
            'secret_key' => $wa::decrypt(PlatformSetting::get('wa_secret_key')), 'base_url' => PlatformSetting::get('wa_base_url'),
        ]);
        try {
            if (empty($data['phone'])) {
                return back()->with('success', $sender->test());
            }
            $log = $wa->sendNow($sender, null, 'test', $data['phone'], 'Tes WhatsApp platform '.config('fnb.product_name').'.');

            return $log?->status === 'sent' ? back()->with('success', 'Pesan tes terkirim ke '.$log->to.'.') : back()->with('error', 'Gagal: '.($log?->error ?? 'nomor tidak valid'));
        } catch (\Throwable $e) {
            return back()->with('error', 'Uji koneksi gagal: '.$e->getMessage());
        }
    }

    protected function plain(array $a): array
    {
        return array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $a);
    }
}
