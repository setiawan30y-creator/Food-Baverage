@extends('layouts.app')
@section('title', $batch->number)
@section('content')
<div class="page-head"><div><a href="{{ route('production.index') }}">← Produksi</a><h1>{{ $batch->number }}</h1><p>{{ $batch->recipe->name }} · {{ $batch->produced_at->format('d/m/Y H:i') }} · {{ $batch->user?->name }}</p></div></div>
<div class="grid g-side">
  <div class="card table-wrap"><table class="table">
    <thead><tr><th>Bahan terpakai</th><th class="num">Jumlah</th><th class="num">Biaya/satuan</th><th class="num">Total</th></tr></thead><tbody>
    @foreach($batch->consumptions as $c)<tr><td>{{ $c->item->name }}</td><td class="num">{{ qty($c->qty) }} {{ $c->item->base_unit }}</td><td class="num">{{ number_format($c->unit_cost, 2, ',', '.') }}</td><td class="num">{{ rupiah($c->total_cost) }}</td></tr>@endforeach
    </tbody><tfoot><tr><td colspan="3">Total biaya batch</td><td class="num">{{ rupiah($batch->total_cost) }}</td></tr></tfoot></table></div>
  <div class="card card-pad"><dl class="kv">
    <dt>Hasil rencana</dt><dd>{{ qty($batch->planned_yield) }} {{ $batch->recipe->outputItem->base_unit }}</dd>
    <dt>Hasil aktual</dt><dd>{{ qty($batch->actual_yield) }} {{ $batch->recipe->outputItem->base_unit }}</dd>
    <dt>Selisih</dt><dd class="{{ $batch->variance_qty < 0 ? 'diff-neg' : '' }}">{{ qty($batch->variance_qty) }}</dd>
    <dt>HPP per {{ $batch->recipe->outputItem->base_unit }}</dt><dd>{{ number_format($batch->unit_cost, 4, ',', '.') }}</dd>
  </dl>@if($batch->variance_reason)<p style="margin-top:10px"><small>Alasan selisih:</small> {{ $batch->variance_reason }}</p>@endif</div>
</div>
@endsection
