@extends('layouts.app')
@section('title', 'Laporan Stok')
@section('content')
<div class="page-head"><div><h1>Laporan</h1><p>Pemakaian teoritis (dari resep × penjualan & produksi) dibandingkan dengan waste dan selisih opname. Diurutkan dari kerugian terbesar.</p></div></div>
@include('reports._head')
<div class="card table-wrap"><table class="table table-compact">
  <thead><tr><th>Bahan</th><th class="num">Masuk</th><th class="num">Terpakai jual</th><th class="num">Terpakai produksi</th><th class="num">Waste</th><th class="num">Selisih opname/penyesuaian</th><th class="num">Nilai kerugian</th></tr></thead><tbody>
  @forelse($rows as $r)<tr><td>{{ $r['item']?->name }} <small>{{ $r['item']?->base_unit }}</small></td><td class="num">{{ qty($r['in']) }}</td><td class="num">{{ qty($r['sale']) }}</td><td class="num">{{ qty($r['production']) }}</td><td class="num">{{ qty($r['waste']) }}</td>
    <td class="num {{ $r['opname'] < 0 ? 'diff-neg' : '' }}">{{ qty($r['opname']) }}</td><td class="num">@if($r['loss_value'] > 0)<span class="badge badge-danger">{{ rupiah($r['loss_value']) }}</span>@else — @endif</td></tr>
  @empty <tr><td colspan="7" class="empty">Tidak ada pergerakan.</td></tr>@endforelse
</tbody></table></div>
@endsection
