@extends('layouts.app')
@section('title', 'Laporan Penjualan')
@section('content')
<div class="page-head"><div><h1>Laporan</h1><p>Hanya transaksi lunas yang dihitung.</p></div></div>
@include('reports._head', ['scoped' => true, 'exportable' => true])
@php $sum = fn ($k) => $daily->sum($k); @endphp
<div class="grid g4" style="margin-bottom:16px">
  <div class="card stat"><div class="label">Penjualan bersih</div><div class="value">{{ rupiah($sum('total')) }}</div><div class="hint">{{ $sum('orders') }} transaksi</div></div>
  <div class="card stat"><div class="label">Diskon</div><div class="value">{{ rupiah($sum('discount')) }}</div></div>
  <div class="card stat"><div class="label">HPP</div><div class="value">{{ rupiah($sum('cost')) }}</div></div>
  <div class="card stat"><div class="label">Laba kotor</div><div class="value">{{ rupiah($sum('subtotal') - $sum('discount') - $sum('cost')) }}</div><div class="hint">sebelum pajak & service</div></div>
</div>
<div class="grid g-side">
  <div class="card table-wrap"><table class="table">
    <thead><tr><th>Tanggal</th><th class="num">Transaksi</th><th class="num">Subtotal</th><th class="num">Diskon</th><th class="num">Pajak+Service</th><th class="num">Total</th><th class="num">HPP</th></tr></thead><tbody>
    @forelse($daily as $d)<tr><td>{{ \Illuminate\Support\Carbon::parse($d->d)->translatedFormat('D d/m') }}</td><td class="num">{{ $d->orders }}</td><td class="num">{{ rupiah($d->subtotal) }}</td><td class="num">{{ rupiah($d->discount) }}</td><td class="num">{{ rupiah($d->tax + $d->service) }}</td><td class="num">{{ rupiah($d->total) }}</td><td class="num">{{ rupiah($d->cost) }}</td></tr>
    @empty <tr><td colspan="7" class="empty">Tidak ada penjualan pada periode ini.</td></tr>@endforelse
  </tbody></table></div>
  <div class="stack">
    <div class="card"><div class="card-head"><h3>Rekonsiliasi pembayaran</h3></div><table class="table"><tbody>
      @forelse($payments as $p)<tr><td>{{ config('fnb.payment_methods.'.$p->method, $p->method) }}</td><td class="num">{{ $p->count }}×</td><td class="num">{{ rupiah($p->total) }}</td></tr>@empty<tr><td class="empty">—</td></tr>@endforelse
    </tbody></table></div>
    <div class="card"><div class="card-head"><h3>Per kanal</h3></div><table class="table"><tbody>
      @foreach($byChannel as $c)<tr><td>{{ ['pos'=>'Kasir','qr'=>'QR Meja','order_ahead'=>'Order Ahead'][$c->channel] ?? $c->channel }}</td><td class="num">{{ $c->orders }}</td><td class="num">{{ rupiah($c->total) }}</td></tr>@endforeach
    </tbody></table></div>
    <div class="card"><div class="card-head"><h3>Per kasir</h3></div><table class="table"><tbody>
      @foreach($byCashier as $c)<tr><td>{{ $c->cashier?->name ?? '—' }}</td><td class="num">{{ $c->orders }}</td><td class="num">{{ rupiah($c->total) }}</td></tr>@endforeach
    </tbody></table></div>
    @if($byBranch->count() > 1)<div class="card"><div class="card-head"><h3>Per cabang</h3></div><table class="table"><tbody>
      @foreach($byBranch as $c)<tr><td>{{ $c->branch?->name }}</td><td class="num">{{ $c->orders }}</td><td class="num">{{ rupiah($c->total) }}</td></tr>@endforeach
    </tbody></table></div>@endif
  </div>
</div>
@endsection
