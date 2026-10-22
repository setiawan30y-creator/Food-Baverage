@extends('layouts.app')
@section('title', 'Pelanggan')
@section('content')
<div class="page-head"><div><h1>Pelanggan</h1><p>Tercatat otomatis saat pelanggan mengisi nomor HP. Poin: 1 per Rp10.000 transaksi lunas.</p></div></div>
<form class="filters"><div class="field"><input type="search" name="q" value="{{ request('q') }}" placeholder="Nama / No. HP"></div><button class="btn">Cari</button></form>
<div class="card table-wrap"><table class="table">
  <thead><tr><th>Nama</th><th>No. HP</th><th class="num">Pesanan</th><th class="num">Total belanja</th><th class="num">Poin</th><th>Promo</th></tr></thead><tbody>
  @forelse($customers as $c)<tr><td>{{ $c->name }}</td><td>{{ $c->phone }}</td><td class="num">{{ $c->orders_count }}</td><td class="num">{{ rupiah($c->spent) }}</td><td class="num">{{ $c->points }}</td><td>{!! $c->marketing_consent ? '<span class="badge badge-ok">Setuju</span>' : '<span class="badge badge-muted">Tidak</span>' !!}</td></tr>
  @empty <tr><td colspan="6" class="empty">Belum ada pelanggan.</td></tr>@endforelse
</tbody></table>{{ $customers->links() }}</div>
@endsection
