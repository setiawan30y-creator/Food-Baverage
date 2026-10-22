@extends('layouts.app')
@section('title', 'Integrasi')
@section('content')
{{-- Pengaturan payment gateway & WhatsApp gateway. @2026-Alifiandra --}}
<div class="page-head"><div><h1>Integrasi</h1><p>Payment gateway untuk QRIS & Virtual Account otomatis, dan WhatsApp untuk notifikasi pelanggan & pemilik.</p></div></div>

<div class="grid g2" style="align-items:start">
  {{-- ============ PAYMENT GATEWAY ============ --}}
  <div class="card">
    <div class="card-head" style="display:flex"><h3>Payment gateway</h3>
      @if(!$hasPayment)<span class="badge badge-muted">Tidak termasuk paket</span>
      @elseif($payment->is_active)<span class="badge badge-ok">Aktif · {{ config('fnb.gateways.'.$payment->provider.'.label') }}</span>
      @else<span class="badge badge-muted">Nonaktif</span>@endif
    </div>
    <div class="card-pad">
    @if(!$hasPayment)
      <p class="muted">Paket Anda belum termasuk payment gateway. Upgrade di menu <a href="{{ route('subscription.index') }}">Langganan</a>.</p>
    @else
      @php $chan = $payment->setting('channels', ['qris']); @endphp
      <form method="POST" action="{{ route('settings.integrations.payment') }}" x-data="{ p: @js($payment->provider) }">
        @csrf
        <div class="field"><label for="pg_provider">Penyedia</label>
          <select id="pg_provider" name="provider" x-model="p">@foreach(config('fnb.gateways') as $k => $g)<option value="{{ $k }}">{{ $g['label'] }}</option>@endforeach</select>
        </div>
        @foreach(config('fnb.gateways') as $k => $g)
          <div x-show="p === '{{ $k }}'">
            <p class="muted" style="font-size:13px">{{ $g['help'] }}</p>
            @foreach($g['fields'] as $f => $label)
              @php $mask = $payment->provider === $k ? $payment->masked($f) : null; @endphp
              <div class="field"><label for="pg_{{ $k }}_{{ $f }}">{{ $label }}</label>
                <input id="pg_{{ $k }}_{{ $f }}" type="password" name="cred[{{ $f }}]" :disabled="p !== '{{ $k }}'" autocomplete="off" placeholder="{{ $mask ? 'Tersimpan: '.$mask.' — kosongkan bila tidak diubah' : '' }}">
              </div>
            @endforeach
            <div class="field"><label>Metode yang ditawarkan</label>
              <div class="row" style="flex-wrap:wrap">
                @foreach($g['channels'] as $c)
                  <label class="check"><input type="checkbox" name="channels[]" value="{{ $c }}" :disabled="p !== '{{ $k }}'" @checked(in_array($c, $chan, true))> {{ config('fnb.gateway_channels.'.$c) }}</label>
                @endforeach
              </div>
            </div>
          </div>
        @endforeach
        <div class="field" x-show="p === 'midtrans'"><label class="check"><input type="checkbox" name="sandbox" value="1" @checked($payment->setting('sandbox', true))> Mode Sandbox (uji coba, tidak ada uang sungguhan)</label></div>
        <div class="grid g2" style="gap:0 16px">
          <div class="field"><label for="fee_qris_percent">Biaya QRIS (%)</label><input id="fee_qris_percent" type="number" step="0.01" min="0" name="fee_qris_percent" value="{{ $payment->setting('fee_qris_percent', 0.7) }}"></div>
          <div class="field"><label for="fee_va_flat">Biaya VA per transaksi (Rp)</label><input id="fee_va_flat" type="number" step="1" min="0" name="fee_va_flat" value="{{ $payment->setting('fee_va_flat', 4000) }}"></div>
          <div class="field"><label for="account_qris_id">Dana QRIS dicatat ke rekening</label>
            <select id="account_qris_id" name="account_qris_id"><option value="">— otomatis —</option>@foreach($accounts->where('method', 'qris') as $a)<option value="{{ $a->id }}" @selected($payment->setting('account_qris_id') == $a->id)>{{ $a->label() }}</option>@endforeach</select></div>
          <div class="field"><label for="account_va_id">Dana VA dicatat ke rekening</label>
            <select id="account_va_id" name="account_va_id"><option value="">— otomatis —</option>@foreach($accounts->where('method', 'transfer') as $a)<option value="{{ $a->id }}" @selected($payment->setting('account_va_id') == $a->id)>{{ $a->label() }}</option>@endforeach</select></div>
        </div>
        <p class="muted" style="font-size:13px;margin-top:0">Biaya dipotong otomatis dari saldo rekening di <a href="{{ route('finance.index') }}">Saldo Kas & Rekening</a>. Sesuaikan dengan tarif di kontrak gateway Anda.</p>
        <div class="field"><label class="check"><input type="checkbox" name="public_pay" value="1" @checked($payment->setting('public_pay', true))> Pelanggan Order Ahead & QR meja bisa bayar sendiri dari halaman lacak pesanan</label></div>
        <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked($payment->is_active)> <b>Aktifkan payment gateway</b></label></div>
        <div class="row"><button class="btn btn-primary">Simpan</button></div>
      </form>
      @if($payment->exists)
        <form method="POST" action="{{ route('settings.integrations.test', 'payment') }}" style="margin-top:10px">@csrf<button class="btn">Uji koneksi</button>
          @if($payment->last_tested_at)<small class="muted"> Terakhir diuji {{ $payment->last_tested_at->diffForHumans() }}{{ $payment->last_error ? ' — gagal' : ' — berhasil' }}</small>@endif
        </form>
        @if($payment->last_error)<div class="alert alert-danger" style="margin-top:10px">{{ $payment->last_error }}</div>@endif
        <hr>
        <label>URL notifikasi (webhook) — tempel di dashboard gateway</label>
        <div class="row" x-data><input type="text" readonly value="{{ route('webhooks.payment', $payment->webhook_token) }}" x-ref="u" style="flex:1"><button type="button" class="btn btn-sm" @click="navigator.clipboard && navigator.clipboard.writeText($refs.u.value)">Salin</button></div>
        <p class="muted" style="font-size:13px">Midtrans: Settings → Configuration → Payment Notification URL. Xendit: Settings → Webhooks (QR code paid & FVA paid).
          Webhook hanya bisa diterima bila aplikasi dapat diakses dari internet (HTTPS). Selama uji coba di komputer lokal, kasir tetap bisa menekan <i>Cek status</i> (Midtrans & QRIS Xendit).</p>
      @endif
    @endif
    </div>
  </div>

  {{-- ============ WHATSAPP ============ --}}
  <div class="card">
    <div class="card-head" style="display:flex"><h3>WhatsApp</h3>
      @if(!$hasWa)<span class="badge badge-muted">Tidak termasuk paket</span>
      @elseif($wa->is_active)<span class="badge badge-ok">Aktif · {{ config('fnb.whatsapp_providers.'.$wa->provider.'.label') }}</span>
      @else<span class="badge badge-muted">Nonaktif</span>@endif
    </div>
    <div class="card-pad">
    @if(!$hasWa)
      <p class="muted">Notifikasi WhatsApp tersedia di paket Pro & Enterprise. Upgrade di menu <a href="{{ route('subscription.index') }}">Langganan</a>.</p>
    @else
      <form method="POST" action="{{ route('settings.integrations.whatsapp') }}" x-data="{ p: @js($wa->provider) }">
        @csrf
        <div class="field"><label for="wa_provider">Penyedia</label>
          <select id="wa_provider" name="provider" x-model="p">@foreach(config('fnb.whatsapp_providers') as $k => $g)<option value="{{ $k }}">{{ $g['label'] }}</option>@endforeach</select>
        </div>
        @foreach(config('fnb.whatsapp_providers') as $k => $g)
          <div x-show="p === '{{ $k }}'">
            <p class="muted" style="font-size:13px">{{ $g['help'] }}</p>
            @foreach($g['fields'] as $f => $label)
              @php $mask = $wa->provider === $k ? ($f === 'base_url' ? $wa->cred($f) : $wa->masked($f)) : null; @endphp
              <div class="field"><label for="wa_{{ $k }}_{{ $f }}">{{ $label }}</label>
                @if($f === 'base_url')
                  <input id="wa_{{ $k }}_{{ $f }}" type="url" name="cred[{{ $f }}]" :disabled="p !== '{{ $k }}'" value="{{ $mask }}" placeholder="https://…">
                @else
                  <input id="wa_{{ $k }}_{{ $f }}" type="password" name="cred[{{ $f }}]" :disabled="p !== '{{ $k }}'" autocomplete="off" placeholder="{{ $mask ? 'Tersimpan: '.$mask.' — kosongkan bila tidak diubah' : '' }}">
                @endif
              </div>
            @endforeach
          </div>
        @endforeach
        <p class="muted" style="font-size:13px">Gunakan nomor WhatsApp khusus usaha. Gateway tidak resmi berisiko diblokir WhatsApp bila mengirim pesan massal/promosi — aplikasi ini hanya mengirim pesan transaksi.</p>
        <div class="field"><label for="owner_phones">Nomor pemilik untuk ringkasan closing</label><input id="owner_phones" name="owner_phones" value="{{ $wa->setting('owner_phones') }}" placeholder="08xxxxxxxxxx, pisahkan dengan koma"></div>
        @foreach(config('fnb.wa_events') as $key => $def)
          @php $ev = data_get($wa->settings, 'events.'.$key, []); $on = $ev['enabled'] ?? in_array($key, ['order_paid', 'order_ready'], true); @endphp
          <details class="disclose" style="border:1px solid var(--border);border-radius:var(--radius-sm);padding:8px 12px;margin-bottom:8px">
            <summary><label class="check" style="display:inline-flex"><input type="checkbox" name="events[{{ $key }}][enabled]" value="1" @checked($on)> {{ $def['label'] }}</label></summary>
            <div class="field" style="margin-top:8px"><textarea name="events[{{ $key }}][template]" rows="4">{{ $ev['template'] ?? $def['template'] }}</textarea>
              <small class="muted">Isian: {{ $def['placeholders'] }}</small></div>
          </details>
        @endforeach
        <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked($wa->is_active)> <b>Aktifkan notifikasi WhatsApp</b></label></div>
        <button class="btn btn-primary">Simpan</button>
      </form>
      @if($wa->exists)
        <div class="row" style="margin-top:10px;flex-wrap:wrap">
          <form method="POST" action="{{ route('settings.integrations.test', 'whatsapp') }}">@csrf<button class="btn">Uji koneksi</button></form>
          <form method="POST" action="{{ route('settings.integrations.wa-test') }}" class="row">@csrf<input name="phone" placeholder="08xxxxxxxxxx" class="input-sm" style="width:150px" required><button class="btn">Kirim pesan tes</button></form>
        </div>
        @if($wa->last_error)<div class="alert alert-danger" style="margin-top:10px">{{ $wa->last_error }}</div>@endif
      @endif
    @endif
    </div>
  </div>
</div>

<div class="grid g2" style="margin-top:16px;align-items:start">
  <div class="card table-wrap">
    <div class="card-head"><h3>Transaksi gateway terakhir</h3></div>
    <table class="table table-compact">
      <thead><tr><th>Waktu</th><th>Pesanan</th><th>Metode</th><th class="num">Nominal</th><th>Status</th></tr></thead>
      <tbody>
      @forelse($charges as $c)
        <tr><td>{{ $c->created_at->format('d/m H:i') }}</td><td>@if($c->order)<a href="{{ route('orders.show', $c->order_id) }}">{{ $c->order->number }}</a>@else — @endif</td>
          <td>{{ $c->channelLabel() }}</td><td class="num">{{ rupiah($c->amount) }}</td><td>{!! status_badge($c->status) !!}</td></tr>
      @empty <tr><td colspan="5" class="empty">Belum ada transaksi.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card table-wrap">
    <div class="card-head"><h3>Pesan WhatsApp terakhir</h3></div>
    <table class="table table-compact">
      <thead><tr><th>Waktu</th><th>Tujuan</th><th>Jenis</th><th>Status</th></tr></thead>
      <tbody>
      @forelse($messages as $m)
        <tr><td>{{ $m->created_at->format('d/m H:i') }}</td><td class="mono">{{ $m->to }}</td><td>{{ config('fnb.wa_events.'.$m->event.'.label', $m->event === 'test' ? 'Pesan tes' : $m->event) }}</td>
          <td>{!! status_badge($m->status) !!}@if($m->error)<br><small class="muted">{{ \Illuminate\Support\Str::limit($m->error, 60) }}</small>@endif</td></tr>
      @empty <tr><td colspan="4" class="empty">Belum ada pesan.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
