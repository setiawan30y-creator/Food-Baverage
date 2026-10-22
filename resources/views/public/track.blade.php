@extends('layouts.public')
@section('title', 'Status Pesanan '.$order->number)
@section('subtitle', $order->branch->name)
@section('content')
@php $steps = ['new' => 'Diterima outlet', 'accepted' => 'Dikonfirmasi', 'preparing' => 'Sedang dibuat', 'ready' => $order->type === 'pickup' ? 'Siap diambil' : 'Siap disajikan', 'completed' => 'Selesai']; $keys = array_keys($steps); @endphp
<div x-data="track()" x-init="poll()">
  <div class="card card-pad" style="text-align:center">
    <small class="muted">Nomor pesanan</small>
    <h1 style="font-size:26px">{{ $order->number }}</h1>
    @if($order->pickup_code)
      <div style="margin:14px auto;padding:12px;border:2px dashed var(--primary);border-radius:12px;max-width:260px">
        <small>Kode pengambilan</small><div class="mono" style="font-size:34px;font-weight:800;letter-spacing:.15em">{{ $order->pickup_code }}</div>
        <small>Tunjukkan ke kasir saat mengambil</small></div>
      <p>Ambil sekitar pukul <b>{{ $order->pickup_at?->format('H:i') }}</b></p>
    @endif
    <template x-if="status === 'cancelled'"><div class="alert alert-danger">Pesanan dibatalkan. Hubungi outlet untuk informasi.</div></template>
    <div x-show="status !== 'cancelled'">
      <div class="steps" style="margin:16px 0 8px">@foreach($keys as $i => $k)<span :class="{ on: idx >= {{ $i }} }"></span>@endforeach</div>
      <h2 x-text="labels[status] || status"></h2>
      <p class="muted" x-text="paid ? 'Pembayaran: lunas' : @js(count($payChannels) ? 'Pembayaran: belum dibayar' : 'Pembayaran: di kasir')"></p>
    </div>
    <small class="muted" x-text="online ? 'Diperbarui otomatis · ' + updated : 'Koneksi terputus — status mungkin belum terbaru'"></small>
  </div>
  @if(count($payChannels))
  <div class="card card-pad" style="margin-top:12px;text-align:center" x-show="!paid && status !== 'cancelled'">
    <h3 style="margin-bottom:6px">Bayar sekarang</h3>
    <p class="muted" style="margin:0 0 10px">Total {{ rupiah($order->total) }} — lunas otomatis setelah pembayaran berhasil.</p>
    <div class="row" style="justify-content:center;flex-wrap:wrap" x-show="!charge">
      @foreach($payChannels as $c)<button type="button" class="btn" @click="startPay(@js($c['id']))" :disabled="charging">{{ $c['label'] }}</button>@endforeach
    </div>
    <p class="muted" x-show="charging">Menyiapkan pembayaran…</p>
    <p class="error-text" x-show="payError" x-text="payError"></p>
    <template x-if="charge">
      <div>
        <div x-show="charge.qr_string || charge.qr_image">
          <div x-ref="qr" style="display:flex;justify-content:center;margin:8px 0"></div>
          <img x-show="!charge.qr_string && charge.qr_image" :src="charge.qr_image" alt="QRIS" style="width:220px;height:220px;margin:auto">
          <small class="muted">Pindai dengan aplikasi m-banking atau e-wallet apa pun. Di HP ini? Simpan tangkapan layar lalu buka dari galeri di aplikasi pembayaran.</small>
        </div>
        <div x-show="charge.va_number" style="margin:8px 0">
          <small class="muted" x-text="'Transfer ke nomor ' + charge.channel_label"></small>
          <div class="mono" style="font-size:24px;font-weight:800" x-text="charge.va_number"></div>
          <button type="button" class="btn btn-sm" @click="navigator.clipboard && navigator.clipboard.writeText(charge.va_number)">Salin nomor</button>
        </div>
        <p class="muted" x-text="'Berlaku sampai pukul ' + (charge.expires_label || '-') + ' · nominal ' + rupiah(charge.amount)"></p>
        <div class="alert alert-info" x-text="payText"></div>
        <button type="button" class="btn btn-sm btn-ghost" @click="charge=null; clearInterval(payTimer)">Pilih metode lain</button>
      </div>
    </template>
  </div>
  @endif
  <div class="card card-pad" style="margin-top:12px">
    @foreach($order->items as $i)<div class="row between" style="padding:4px 0"><span>{{ $i->qty }}× {{ $i->name }}@if($i->modifiers)<br><small>{{ collect($i->modifiers)->pluck('name')->implode(', ') }}</small>@endif</span><span class="mono">{{ rupiah($i->subtotal) }}</span></div>@endforeach
    <hr><div class="row between"><b>Total</b><b class="mono">{{ rupiah($order->total) }}</b></div>
    <a x-show="paid" href="{{ route('track.receipt', $order->tracking_token) }}" class="btn btn-block" style="margin-top:12px">Lihat nota digital</a>
  </div>
  <p class="muted" style="text-align:center;margin-top:10px;font-size:12px">Simpan halaman ini untuk memantau pesanan.</p>
</div>
@endsection
@push('scripts')
@if(count($payChannels))<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>@endif
<script>
function track() {
  const keys = @js($keys);
  return {
    status: @js($order->status), paid: @js($order->payment_status === 'paid'), labels: @js($steps), updated: '', online: true,
    charge: null, charging: false, payError: '', payText: '', payTimer: null,
    async startPay(channel) {
      this.charging = true; this.payError = '';
      try {
        const res = await fetch(@js(route('track.pay', $order->tracking_token)), { method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
          body: JSON.stringify({ channel }) });
        const j = await res.json();
        if (!res.ok) throw new Error(j.message || 'Gagal membuat pembayaran.');
        this.charge = j.charge; this.payText = 'Menunggu pembayaran… halaman ini diperbarui otomatis.';
        this.$nextTick(() => { const el = this.$refs.qr; if (el && this.charge.qr_string && window.QRCode) { el.innerHTML = ''; new QRCode(el, { text: this.charge.qr_string, width: 220, height: 220 }); } });
        clearInterval(this.payTimer); this.payTimer = setInterval(() => this.checkPay(), 5000);
      } catch (e) { this.payError = e.message; }
      this.charging = false;
    },
    async checkPay() {
      try {
        const r = await fetch(@js(route('track.pay.status', [$order->tracking_token, '__REF__'])).replace('__REF__', this.charge.reference), { headers: { Accept: 'application/json' } });
        const j = await r.json();
        if (j.order_paid) { clearInterval(this.payTimer); this.paid = true; this.charge = null; this.load(); }
        else if (j.status === 'expired' || j.status === 'failed') { clearInterval(this.payTimer); this.payText = 'Pembayaran kedaluwarsa/gagal. Silakan buat ulang.'; }
      } catch (e) {}
    },
    get idx() { return keys.indexOf(this.status); },
    poll() { this.load(); setInterval(() => this.load(), 8000); },
    async load() {
      try { const r = await fetch(@js(route('track.status', $order->tracking_token)), { headers: { Accept: 'application/json' } }); const j = await r.json();
        this.status = j.status; this.paid = j.payment_status === 'paid'; this.updated = j.updated; this.online = true; } catch (e) { this.online = false; }
    },
  };
}
</script>
@endpush
