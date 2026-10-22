@extends('layouts.app')
@section('title', 'Resep & HPP')
@section('content')
<div class="page-head"><div><h1>Resep & HPP</h1><p>HPP menu mengambil biaya sub-recipe (bukan bahan mentahnya lagi) sehingga tidak terhitung ganda.</p></div>
  <div class="row"><a href="{{ route('recipes.create', ['type' => 'sub_recipe']) }}" class="btn">+ Sub-recipe (racikan)</a><a href="{{ route('recipes.create') }}" class="btn btn-primary">+ Resep menu</a></div></div>
@if($menusWithout->isNotEmpty())
  <div class="alert alert-warn">Menu tanpa resep (stok & HPP tidak terhitung): @foreach($menusWithout as $m)<a href="{{ route('recipes.create', ['menu_id' => $m->id]) }}">{{ $m->name }}</a>@if(!$loop->last), @endif @endforeach</div>
@endif
@foreach(['sub_recipe' => 'Sub-recipe / racikan', 'menu' => 'Resep menu'] as $type => $label)
  <h2 style="margin:18px 0 10px">{{ $label }}</h2>
  <div class="card table-wrap"><table class="table">
    <thead><tr><th>Nama</th><th>Versi</th><th class="num">Hasil (yield)</th><th class="num">Jumlah bahan</th><th class="num">{{ $type === 'menu' ? 'HPP / porsi' : 'HPP / satuan' }}</th>@if($type==='menu')<th class="num">Harga</th><th class="num">Margin</th>@endif</tr></thead><tbody>
    @forelse($rows->filter(fn ($r) => $r['recipe']->type === $type) as $r)
      @php $rc = $r['recipe']; @endphp
      <tr><td><a href="{{ route('recipes.edit', $rc) }}"><b>{{ $rc->name }}</b></a></td><td>v{{ $rc->version }}</td>
        <td class="num">{{ qty($rc->yield_qty) }} {{ $type === 'menu' ? 'porsi' : $rc->outputItem?->base_unit }}</td><td class="num">{{ $rc->items->count() }}</td>
        <td class="num">{{ $type === 'menu' ? rupiah($r['cost']) : number_format($r['cost'], 2, ',', '.') }}</td>
        @if($type==='menu')<td class="num">{{ rupiah($r['price']) }}</td><td class="num"><span class="badge {{ $r['margin'] < 40 ? 'badge-warn' : 'badge-ok' }}">{{ number_format($r['margin'], 0) }}%</span></td>@endif</tr>
    @empty <tr><td colspan="7" class="empty">Belum ada.</td></tr>
    @endforelse
  </tbody></table></div>
@endforeach
@endsection
