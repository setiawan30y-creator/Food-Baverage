@extends('layouts.app')
@section('title', 'Produksi Racikan')
@section('content')
<div class="page-head"><div><h1>Produksi racikan</h1><p>Produksi batch saus, sambal, bumbu, marinasi, adonan, nasi, kaldu. Stok bahan baku berkurang, stok racikan bertambah sesuai yield aktual.</p></div></div>
<div class="grid g-side">
  <div class="card table-wrap"><table class="table">
    <thead><tr><th>Batch</th><th>Racikan</th><th class="num">Rencana</th><th class="num">Aktual</th><th class="num">Selisih</th><th class="num">HPP / satuan</th></tr></thead><tbody>
    @forelse($batches as $b)
      <tr><td><a href="{{ route('production.show', $b) }}"><b>{{ $b->number }}</b></a><br><small>{{ $b->produced_at->format('d/m H:i') }} · {{ $b->user?->name }}</small></td>
        <td>{{ $b->recipe->outputItem?->name }}</td><td class="num">{{ qty($b->planned_yield) }}</td><td class="num">{{ qty($b->actual_yield) }}</td>
        <td class="num {{ $b->variance_qty < 0 ? 'diff-neg' : '' }}">{{ qty($b->variance_qty) }}</td><td class="num">{{ number_format($b->unit_cost, 2, ',', '.') }}</td></tr>
    @empty <tr><td colspan="6" class="empty">Belum ada produksi.</td></tr>
    @endforelse
  </tbody></table>{{ $batches->links() }}</div>
  <div class="card card-pad">
    <h3 style="margin-bottom:10px">Mulai produksi</h3>
    <form method="GET" action="{{ route('production.create') }}">
      <div class="field"><label>Sub-recipe</label><select name="recipe_id" required>@foreach($recipes as $r)<option value="{{ $r->id }}">{{ $r->name }} (hasil {{ qty($r->yield_qty) }} {{ $r->outputItem?->base_unit }})</option>@endforeach</select></div>
      <button class="btn btn-primary btn-block" @disabled($recipes->isEmpty())>Lanjut</button>
    </form>
  </div>
</div>
@endsection
