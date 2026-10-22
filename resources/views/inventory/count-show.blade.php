@extends('layouts.app')
@section('title', $count->number)
@section('content')
<div class="page-head"><div><a href="{{ route('inventory.counts.index') }}">← Stock opname</a><h1>{{ $count->number }}</h1><p>{{ $count->counted_at->format('d/m/Y') }} · {!! status_badge($count->status) !!}</p></div></div>
<form method="POST" action="{{ route('inventory.counts.update', $count) }}" class="card table-wrap">
  @csrf @method('PUT')
  <table class="table">
    <thead><tr><th>Bahan</th><th class="num">Stok sistem</th><th class="num">Hitung fisik</th><th class="num">Selisih</th><th class="num">Nilai selisih</th></tr></thead>
    <tbody>
    @foreach($count->lines as $l)
      <tr><td>{{ $l->item->name }} <small>({{ $l->item->base_unit }})</small></td><td class="num">{{ qty($l->system_qty) }}</td>
        <td class="num">@if($count->status==='draft')<input type="number" step="any" min="0" name="counted[{{ $l->id }}]" value="{{ $l->counted_qty }}" class="input-sm" style="width:120px">@else{{ $l->counted_qty !== null ? qty($l->counted_qty) : '—' }}@endif</td>
        <td class="num {{ $l->difference < 0 ? 'diff-neg' : ($l->difference > 0 ? 'diff-pos' : '') }}">{{ $l->counted_qty !== null ? qty($l->difference) : '' }}</td>
        <td class="num">{{ $l->counted_qty !== null ? rupiah($l->difference * $l->unit_cost) : '' }}</td></tr>
    @endforeach
    </tbody>
    <tfoot><tr><td colspan="4">Total nilai selisih</td><td class="num">{{ rupiah($count->lines->sum(fn ($l) => $l->difference * $l->unit_cost)) }}</td></tr></tfoot>
  </table>
  @if($count->status==='draft')
  <div class="card-pad row">
    <button name="action" value="save" class="btn">Simpan hitungan</button>
    @if(auth()->user()->hasAbility('inventory.approve'))<button name="action" value="post" class="btn btn-primary" onclick="return confirm('Posting selisih ke stok? Tidak dapat dibatalkan.')">Posting selisih</button>@endif
    <small class="muted">Baris kosong tidak diubah.</small>
  </div>
  @endif
</form>
@endsection
