@extends('layouts.app')
@section('title', 'Riwayat Penjualan')
@section('content')
{{-- @2026-Alifiandra --}}
<div class="page-head"><div><h1>Riwayat Penjualan</h1><p>Semua pembayaran lunas di cabang aktif, lengkap dengan metode & rekening tujuan.</p></div></div>
<form class="filters">
  <div class="field"><label>Dari</label><input type="date" name="from" value="{{ $from->toDateString() }}"></div>
  <div class="field"><label>Sampai</label><input type="date" name="to" value="{{ $to->toDateString() }}"></div>
  <div class="field"><label>Metode</label><select name="method"><option value="">Semua</option>@foreach(config('fnb.payment_methods') as $k => $l)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $l }}</option>@endforeach</select></div>
  <div class="field"><label>Rekening</label><select name="account"><option value="">Semua</option>@foreach($accounts as $a)<option value="{{ $a->id }}" @selected(request('account') == $a->id)>{{ $a->name }}</option>@endforeach<option value="none" @selected(request('account') === 'none')>Belum ditautkan</option></select></div>
  <div class="field"><label>Cari</label><input type="search" name="q" value="{{ request('q') }}" placeholder="No. pesanan / nama"></div>
  <button class="btn">Tampilkan</button>
  <a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 1]) }}">Ekspor Excel (CSV)</a>
</form>
<div class="grid" style="margin-bottom:16px;grid-template-columns:repeat(auto-fit,minmax(170px,1fr))">
  <div class="card stat"><div class="label">Total penjualan</div><div class="value">{{ rupiah($grand['total']) }}</div><div class="hint">{{ $grand['cnt'] }} transaksi</div></div>
  @foreach(config('fnb.payment_methods') as $k => $l)
    <div class="card stat"><div class="label">{{ $l }}</div><div class="value">{{ rupiah($summary[$k]->total ?? 0) }}</div><div class="hint">{{ (int) ($summary[$k]->cnt ?? 0) }} transaksi</div></div>
  @endforeach
</div>
@if($byAccount->count())
<div class="card table-wrap" style="margin-bottom:16px">
  <div class="card-head"><h3>Per rekening tujuan</h3></div>
  <table class="table table-compact">
    <thead><tr><th>Rekening</th><th>Metode</th><th class="num">Transaksi</th><th class="num">Total</th></tr></thead>
    <tbody>
    @foreach($byAccount as $b)
      <tr><td>{{ $b->account?->name ?? 'Belum ditautkan' }}</td><td>{{ config('fnb.payment_methods.'.$b->method, $b->method) }}</td><td class="num">{{ $b->cnt }}</td><td class="num">{{ rupiah($b->total) }}</td></tr>
    @endforeach
    </tbody>
  </table>
</div>
@endif
<div class="card table-wrap">
  <table class="table">
    <thead><tr><th>No. pesanan</th><th>Waktu bayar</th><th>Kanal</th><th>Pelanggan / Meja</th><th>Kasir</th><th>Metode</th><th>Rekening</th><th class="num">Total</th></tr></thead>
    <tbody>
    @forelse($rows as $p)
      <tr>
        <td>@if($p->order)<a href="{{ route('orders.show', $p->order) }}"><b>{{ $p->order->number }}</b></a>@else — @endif</td>
        <td>{{ $p->paid_at?->format('d/m/Y H:i') }}</td>
        <td>{{ $p->order?->channelLabel() }}</td>
        <td>{{ $p->order?->customer_name ?: '—' }}@if($p->order?->table)<br><small>{{ $p->order->table->name }}</small>@endif</td>
        <td>{{ $p->user?->name ?? '—' }}</td>
        <td>{{ config('fnb.payment_methods.'.$p->method, $p->method) }}@if($p->reference)<br><small class="muted">ref {{ $p->reference }}</small>@endif</td>
        <td>{{ $p->account?->name ?? '—' }}</td>
        <td class="num">{{ rupiah($p->amount) }}</td>
      </tr>
    @empty <tr><td colspan="8" class="empty">Tidak ada penjualan lunas pada periode ini.</td></tr>
    @endforelse
    </tbody>
  </table>
  {{ $rows->links() }}
</div>
@endsection
