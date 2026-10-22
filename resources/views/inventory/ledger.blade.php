@extends('layouts.app')
@section('title', 'Kartu stok '.$item->name)
@section('content')
<div class="page-head"><div><a href="{{ route('inventory.stock') }}">← Stok</a><h1>Kartu stok: {{ $item->name }}</h1><p>Saldo saat ini {{ qty($balance) }} {{ $item->base_unit }} · cabang {{ $currentBranch->name }}</p></div></div>
@php $typeLabels = ['opening'=>'Saldo awal','purchase'=>'Pembelian','sale'=>'Penjualan','sale_return'=>'Batal jual','production_in'=>'Hasil produksi','production_out'=>'Bahan produksi','waste'=>'Waste','adjustment'=>'Penyesuaian','opname'=>'Stock opname','transfer_in'=>'Transfer masuk','transfer_out'=>'Transfer keluar']; @endphp
<div class="card table-wrap">
  <table class="table">
    <thead><tr><th>Waktu</th><th>Jenis</th><th>Keterangan</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Biaya/satuan</th><th>Oleh</th></tr></thead>
    <tbody>
    @forelse($movements as $m)
      <tr><td>{{ $m->created_at->format('d/m/y H:i') }}</td><td>{{ $typeLabels[$m->type] ?? $m->type }}</td><td>{{ $m->note }}</td>
        <td class="num">{{ $m->qty > 0 ? qty($m->qty) : '' }}</td><td class="num">{{ $m->qty < 0 ? qty(abs($m->qty)) : '' }}</td>
        <td class="num">{{ number_format($m->unit_cost, 2, ',', '.') }}</td><td><small>{{ $m->user?->name ?? 'Sistem' }}</small></td></tr>
    @empty <tr><td colspan="7" class="empty">Belum ada pergerakan.</td></tr>
    @endforelse
    </tbody>
  </table>
  {{ $movements->links() }}
</div>
@endsection
