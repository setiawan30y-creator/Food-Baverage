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
      <p class="muted" x-text="paid ? 'Pembayaran: lunas' : 'Pembayaran: di kasir'"></p>
    </div>
    <small class="muted" x-text="online ? 'Diperbarui otomatis · ' + updated : 'Koneksi terputus — status mungkin belum terbaru'"></small>
  </div>
  <div class="card card-pad" style="margin-top:12px">
    @foreach($order->items as $i)<div class="row between" style="padding:4px 0"><span>{{ $i->qty }}× {{ $i->name }}@if($i->modifiers)<br><small>{{ collect($i->modifiers)->pluck('name')->implode(', ') }}</small>@endif</span><span class="mono">{{ rupiah($i->subtotal) }}</span></div>@endforeach
    <hr><div class="row between"><b>Total</b><b class="mono">{{ rupiah($order->total) }}</b></div>
    <a x-show="paid" href="{{ route('track.receipt', $order->tracking_token) }}" class="btn btn-block" style="margin-top:12px">Lihat nota digital</a>
  </div>
  <p class="muted" style="text-align:center;margin-top:10px;font-size:12px">Simpan halaman ini untuk memantau pesanan.</p>
</div>
@endsection
@push('scripts')
<script>
function track() {
  const keys = @js($keys);
  return {
    status: @js($order->status), paid: @js($order->payment_status === 'paid'), labels: @js($steps), updated: '', online: true,
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
