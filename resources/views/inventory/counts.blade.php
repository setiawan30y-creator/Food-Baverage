@extends('layouts.app')
@section('title', 'Stock Opname')
@section('content')
<div class="page-head"><div><h1>Stock opname</h1><p>Bandingkan stok teoritis (sistem) dengan hitungan fisik. Selisih diposting ke ledger.</p></div>
  <form method="POST" action="{{ route('inventory.counts.store') }}">@csrf<button class="btn btn-primary">Mulai opname baru</button></form></div>
@include('inventory._tabs')
<div class="card table-wrap">
  <table class="table"><thead><tr><th>Nomor</th><th>Tanggal</th><th>Oleh</th><th>Status</th></tr></thead><tbody>
  @forelse($counts as $c)
    <tr><td><a href="{{ route('inventory.counts.show', $c) }}">{{ $c->number }}</a></td><td>{{ $c->counted_at->format('d/m/Y') }}</td><td>{{ $c->user?->name }}</td><td>{!! status_badge($c->status) !!}</td></tr>
  @empty <tr><td colspan="4" class="empty">Belum ada stock opname.</td></tr>
  @endforelse
  </tbody></table>
  {{ $counts->links() }}
</div>
@endsection
