@extends('layouts.app')
@section('title', 'Master Bahan')
@section('content')
<div class="page-head"><div><h1>Master bahan</h1><p>Bahan baku (dibeli/langsung pakai) dan bahan racikan hasil produksi (sub-recipe).</p></div>
  <a href="{{ route('inventory.items.create') }}" class="btn btn-primary"><x-icon name="plus" style="width:16px;height:16px"/> Bahan baru</a></div>
@include('inventory._tabs')
<form class="filters">
  <div class="field"><input type="search" name="q" value="{{ request('q') }}" placeholder="Cari bahan"></div>
  <div class="field"><select name="type"><option value="">Semua jenis</option><option value="raw" @selected(request('type')==='raw')>Bahan baku</option><option value="sub_recipe" @selected(request('type')==='sub_recipe')>Racikan (sub-recipe)</option></select></div>
  <button class="btn">Filter</button>
</form>
<div class="card table-wrap">
  <table class="table">
    <thead><tr><th>Nama</th><th>Jenis</th><th>Satuan dasar</th><th>Konversi</th><th class="num">HPP / satuan</th><th class="num">Stok</th><th class="num">Min.</th></tr></thead>
    <tbody>
    @foreach($items as $i)
      <tr>
        <td><a href="{{ route('inventory.items.edit', $i) }}"><b>{{ $i->name }}</b></a>@unless($i->is_active) <span class="badge badge-muted">Nonaktif</span>@endunless<br><small>{{ $i->category }}</small></td>
        <td>{!! $i->type === 'sub_recipe' ? '<span class="badge badge-primary">Racikan</span>' : '<span class="badge badge-muted">Bahan baku</span>' !!}</td>
        <td>{{ $i->base_unit }}</td>
        <td><small>{{ $i->conversions->map(fn ($c) => '1 '.$c->unit.' = '.qty($c->factor).' '.$i->base_unit)->implode('; ') ?: '—' }}</small></td>
        <td class="num">{{ number_format($costs[$i->id], 2, ',', '.') }}</td>
        <td class="num">{{ qty($balances[$i->id] ?? 0) }}</td>
        <td class="num">{{ qty($i->min_stock) }}</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endsection
