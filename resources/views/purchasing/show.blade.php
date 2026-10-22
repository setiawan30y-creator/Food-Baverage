@extends('layouts.app')
@section('title', $purchase->number)
@section('content')
<div class="page-head"><div><a href="{{ route('purchases.index') }}">← Pembelian</a><h1>{{ $purchase->number }}</h1><p>{{ $purchase->date->format('d/m/Y') }} · {{ $purchase->supplier?->name ?? 'Tanpa supplier' }} · {!! status_badge($purchase->status) !!} {!! status_badge($purchase->payment_status) !!}</p></div>
  <div class="row">
    @if($purchase->status==='draft')
      <form method="POST" action="{{ route('purchases.receive', $purchase) }}">@csrf<button class="btn btn-primary">Terima barang</button></form>
      <form method="POST" action="{{ route('purchases.cancel', $purchase) }}" onsubmit="return confirm('Batalkan pembelian?')">@csrf<button class="btn btn-danger">Batalkan</button></form>
    @endif
  </div></div>
<div class="grid g-side">
  <div class="card table-wrap"><table class="table">
    <thead><tr><th>Bahan</th><th class="num">Jumlah</th><th class="num">= satuan dasar</th><th class="num">Harga</th><th class="num">Subtotal</th><th class="num">Riwayat harga / dasar</th></tr></thead><tbody>
    @foreach($purchase->items as $l)
      @php $h = $priceHistory->get($l->inventory_item_id); $perBase = $l->factor > 0 ? $l->price / $l->factor : 0; @endphp
      <tr><td>{{ $l->item->name }}</td><td class="num">{{ qty($l->qty) }} {{ $l->unit }}</td><td class="num">{{ qty($l->base_qty) }} {{ $l->item->base_unit }}</td><td class="num">{{ rupiah($l->price) }}</td><td class="num">{{ rupiah($l->subtotal) }}</td>
        <td class="num"><small>@if($h){{ number_format($h->min_price, 2, ',', '.') }} – {{ number_format($h->max_price, 2, ',', '.') }}@if($perBase > $h->max_price * 1.1 && $h->max_price > 0) <span class="badge badge-warn">lebih mahal</span>@endif @else — @endif</small></td></tr>
    @endforeach
    </tbody><tfoot><tr><td colspan="4">Total</td><td class="num">{{ rupiah($purchase->total) }}</td><td></td></tr></tfoot></table></div>
  <div class="card card-pad">
    <dl class="kv"><dt>Total</dt><dd>{{ rupiah($purchase->total) }}</dd><dt>Dibayar</dt><dd>{{ rupiah($purchase->paid_amount) }}</dd><dt>Sisa utang</dt><dd>{{ rupiah($purchase->total - $purchase->paid_amount) }}</dd></dl>
    @if($purchase->status==='received' && $purchase->payment_status!=='paid')
      <form method="POST" action="{{ route('purchases.pay', $purchase) }}" style="margin-top:14px">@csrf
        <div class="field"><label>Bayar ke supplier</label><input type="number" name="amount" value="{{ (int) ($purchase->total - $purchase->paid_amount) }}" min="1" required></div>
        <button class="btn btn-accent btn-block">Catat pembayaran</button></form>
    @endif
    @if($purchase->note)<p style="margin-top:12px"><small>Catatan:</small> {{ $purchase->note }}</p>@endif
  </div>
</div>
@endsection
