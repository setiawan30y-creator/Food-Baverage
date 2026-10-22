@extends('layouts.app')
@section('title', 'Pesanan')
@section('content')
<div class="page-head">
  <div><h1>Pesanan</h1><p>Semua kanal: kasir, QR meja, dan Order Ahead.</p></div>
  <form method="POST" action="{{ route('orders.pickup') }}" class="row">
    @csrf
    <input type="text" name="code" inputmode="numeric" maxlength="6" placeholder="Kode ambil 6 digit" style="width:180px" required>
    <button class="btn btn-accent">Verifikasi pengambilan</button>
  </form>
</div>
<form class="filters">
  <div class="field"><label>Cari</label><input type="search" name="q" value="{{ request('q') }}" placeholder="No. pesanan / nama"></div>
  <div class="field"><label>Status</label><select name="status"><option value="">Semua</option>@foreach(\App\Models\Order::STATUSES as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ strip_tags(status_badge($s)) }}</option>@endforeach</select></div>
  <div class="field"><label>Kanal</label><select name="channel"><option value="">Semua</option><option value="pos" @selected(request('channel')==='pos')>Kasir</option><option value="qr" @selected(request('channel')==='qr')>QR Meja</option><option value="order_ahead" @selected(request('channel')==='order_ahead')>Order Ahead</option></select></div>
  <div class="field"><label>Bayar</label><select name="payment"><option value="">Semua</option><option value="unpaid" @selected(request('payment')==='unpaid')>Belum bayar</option><option value="paid" @selected(request('payment')==='paid')>Lunas</option><option value="refunded" @selected(request('payment')==='refunded')>Refund</option></select></div>
  <div class="field"><label>Tanggal</label><input type="date" name="date" value="{{ request('date') }}"></div>
  <button class="btn">Terapkan</button>
</form>
<div class="card table-wrap">
  <table class="table">
    <thead><tr><th>No.</th><th>Waktu</th><th>Kanal</th><th>Pelanggan / Meja</th><th>Status</th><th>Bayar</th><th class="num">Total</th></tr></thead>
    <tbody>
    @forelse($orders as $o)
      <tr>
        <td><a href="{{ route('orders.show', $o) }}"><b>{{ $o->number }}</b></a></td>
        <td>{{ $o->created_at->format('d/m H:i') }}@if($o->pickup_at)<br><small>Ambil {{ $o->pickup_at->format('H:i') }}</small>@endif</td>
        <td>{{ $o->channelLabel() }}<br><small>{{ $o->typeLabel() }}</small></td>
        <td>{{ $o->customer_name ?: '—' }}@if($o->table)<br><small>{{ $o->table->name }}</small>@endif</td>
        <td>{!! status_badge($o->status) !!}</td>
        <td>{!! status_badge($o->payment_status) !!}</td>
        <td class="num">{{ rupiah($o->total) }}</td>
      </tr>
    @empty <tr><td colspan="7" class="empty">Belum ada pesanan.</td></tr>
    @endforelse
    </tbody>
  </table>
  {{ $orders->links() }}
</div>
@endsection
