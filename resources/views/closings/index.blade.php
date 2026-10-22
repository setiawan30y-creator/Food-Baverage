@extends('layouts.app')
@section('title', 'History Closing')
@section('content')
<div class="page-head"><div><h1>History closing</h1><p>Arsip closing final per cabang {{ $currentBranch->name }}.</p></div>
  <a href="{{ route('closings.export', request()->query()) }}" class="btn">Ekspor Excel (CSV)</a></div>
<form class="filters">
  <div class="field"><label>Dari</label><input type="date" name="from" value="{{ request('from') }}"></div>
  <div class="field"><label>Sampai</label><input type="date" name="to" value="{{ request('to') }}"></div>
  @if(auth()->user()->hasAbility('closing.approve'))<div class="field"><label>Kasir</label><select name="user_id"><option value="">Semua</option>@foreach($cashiers as $c)<option value="{{ $c->id }}" @selected(request('user_id')==$c->id)>{{ $c->name }}</option>@endforeach</select></div>@endif
  <div class="field"><label>Nomor</label><input name="number" value="{{ request('number') }}" placeholder="CLS-…"></div>
  <div class="field"><label>Hasil</label><select name="result"><option value="">Semua</option><option value="match" @selected(request('result')==='match')>Sesuai</option><option value="diff" @selected(request('result')==='diff')>Ada selisih</option></select></div>
  <div class="field"><label>Persetujuan</label><select name="approval"><option value="">Semua</option><option value="pending" @selected(request('approval')==='pending')>Menunggu</option><option value="approved" @selected(request('approval')==='approved')>Disetujui</option><option value="rejected" @selected(request('approval')==='rejected')>Ditolak</option></select></div>
  <button class="btn">Terapkan</button>
</form>
<div class="card table-wrap"><table class="table table-compact">
  <thead><tr><th>Nomor</th><th>Final</th><th>Kasir · Shift</th><th class="num">Kas seharusnya</th><th class="num">Kas fisik</th><th class="num">Selisih</th><th class="num">Non-tunai</th><th>Status</th></tr></thead><tbody>
  @forelse($closings as $c)
    <tr><td><a href="{{ route('closings.show', $c) }}"><b>{{ $c->number }}</b></a></td><td>{{ $c->finalized_at->format('d/m/y H:i') }}</td><td>{{ $c->user?->name }}<br><small>{{ $c->shift?->number }}</small></td>
      <td class="num">{{ rupiah($c->expected_cash) }}</td><td class="num">{{ rupiah($c->counted_cash) }}</td>
      <td class="num {{ $c->difference < 0 ? 'diff-neg' : ($c->difference > 0 ? 'diff-pos' : '') }}">{{ $c->difference == 0 ? 'Sesuai' : rupiah($c->difference) }}</td>
      <td class="num">{{ rupiah($c->non_cash_total) }}</td><td>{!! status_badge($c->status) !!} {!! status_badge($c->approval_status) !!}</td></tr>
  @empty <tr><td colspan="8" class="empty">Belum ada closing.</td></tr>
  @endforelse
</tbody></table>{{ $closings->links() }}</div>
@endsection
