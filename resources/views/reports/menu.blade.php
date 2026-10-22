@extends('layouts.app')
@section('title', 'Laporan Menu')
@section('content')
<div class="page-head"><div><h1>Laporan</h1><p>HPP memakai snapshot saat transaksi, tidak berubah walau harga bahan berubah.</p></div></div>
@include('reports._head', ['scoped' => true, 'exportable' => true])
<div class="card table-wrap"><table class="table">
  <thead><tr><th>Menu</th><th class="num">Terjual</th><th class="num">Pendapatan</th><th class="num">HPP</th><th class="num">Laba kotor</th><th style="width:22%">Margin</th></tr></thead><tbody>
  @forelse($rows as $r)<tr><td><b>{{ $r->name }}</b></td><td class="num">{{ qty($r->qty) }}</td><td class="num">{{ rupiah($r->revenue) }}</td><td class="num">{{ rupiah($r->cost) }}</td><td class="num">{{ rupiah($r->revenue - $r->cost) }}</td>
    <td><div class="row" style="flex-wrap:nowrap"><div class="bar" style="flex:1"><span style="width:{{ max(0, min(100, $r->margin)) }}%;{{ $r->margin < 40 ? 'background:var(--warn)' : '' }}"></span></div><small class="mono">{{ number_format($r->margin, 0) }}%</small></div></td></tr>
  @empty <tr><td colspan="6" class="empty">Belum ada data.</td></tr>@endforelse
</tbody></table></div>
@endsection
