@extends('layouts.app')
@section('title', 'Pembelian')
@section('content')
<div class="page-head"><div><h1>Pembelian</h1><p>Penerimaan barang menambah stok dan memperbarui HPP rata-rata.</p></div><a href="{{ route('purchases.create') }}" class="btn btn-primary"><x-icon name="plus" style="width:16px;height:16px"/> Pembelian baru</a></div>
@include('purchasing._tabs')
<div class="card table-wrap"><table class="table">
  <thead><tr><th>Nomor</th><th>Tanggal</th><th>Supplier</th><th>Status</th><th>Pembayaran</th><th class="num">Total</th><th class="num">Utang</th></tr></thead><tbody>
  @forelse($purchases as $p)
    <tr><td><a href="{{ route('purchases.show', $p) }}"><b>{{ $p->number }}</b></a></td><td>{{ $p->date->format('d/m/Y') }}</td><td>{{ $p->supplier?->name ?? '—' }}</td>
      <td>{!! status_badge($p->status) !!}</td><td>{!! status_badge($p->payment_status) !!}</td><td class="num">{{ rupiah($p->total) }}</td><td class="num">{{ $p->status === 'received' ? rupiah($p->total - $p->paid_amount) : '—' }}</td></tr>
  @empty <tr><td colspan="7" class="empty">Belum ada pembelian.</td></tr>
  @endforelse
</tbody></table>{{ $purchases->links() }}</div>
@endsection
