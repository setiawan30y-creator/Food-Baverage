@extends('layouts.app')
@section('title', 'Laporan Waste')
@section('content')
<div class="page-head"><div><h1>Laporan</h1><p>Nilai kerugian waste yang disetujui.</p></div></div>
@include('reports._head')
<div class="grid g3">
  <div class="card"><div class="card-head"><h3>Per bahan</h3></div><table class="table"><tbody>
    @forelse($byItem as $r)<tr><td>{{ $r['item']->name }}<br><small>{{ qty($r['qty']) }} {{ $r['item']->base_unit }}</small></td><td class="num">{{ rupiah($r['total']) }}</td></tr>@empty<tr><td class="empty">—</td></tr>@endforelse
  </tbody></table></div>
  <div class="card"><div class="card-head"><h3>Per alasan</h3></div><table class="table"><tbody>
    @forelse($byReason as $k => $v)<tr><td>{{ \App\Models\WasteRecord::REASONS[$k] ?? $k }}</td><td class="num">{{ rupiah($v) }}</td></tr>@empty<tr><td class="empty">—</td></tr>@endforelse
  </tbody></table></div>
  <div class="card"><div class="card-head"><h3>Per pelapor</h3></div><table class="table"><tbody>
    @forelse($byUser as $r)<tr><td>{{ $r['user']?->name }}<br><small>{{ $r['count'] }} catatan</small></td><td class="num">{{ rupiah($r['total']) }}</td></tr>@empty<tr><td class="empty">—</td></tr>@endforelse
  </tbody></table></div>
</div>
@endsection
