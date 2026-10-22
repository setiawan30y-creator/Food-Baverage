@extends('layouts.app')
@section('title', 'Menu')
@section('content')
<div class="page-head">
  <div><h1>Menu</h1><p>Harga & ketersediaan di bawah berlaku untuk cabang <b>{{ $currentBranch->name }}</b>.</p></div>
  <a href="{{ route('menus.create') }}" class="btn btn-primary"><x-icon name="plus" style="width:16px;height:16px"/> Menu baru</a>
</div>
<div class="grid g-side">
  <div>
    <form class="filters">
      <div class="field"><input type="search" name="q" value="{{ request('q') }}" placeholder="Cari menu"></div>
      <div class="field"><select name="category"><option value="">Semua kategori</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
      <button class="btn">Filter</button>
    </form>
    <div class="card table-wrap">
      <table class="table">
        <thead><tr><th>Menu</th><th class="num">Harga cabang</th><th class="num">HPP</th><th class="num">Margin</th><th>Tersedia</th><th>Habis</th></tr></thead>
        <tbody>
        @forelse($menus as $m)
          @php $s = $settings->get($m->id); $price = (float) ($s?->price ?? $m->price); $cost = $costs[$m->id]; @endphp
          <tr x-data="{ avail: {{ ($s?->is_available ?? true) ? 'true' : 'false' }}, sold: {{ ($s?->sold_out ?? false) ? 'true' : 'false' }} }">
            <td><a href="{{ route('menus.edit', $m) }}"><b>{{ $m->name }}</b></a>@unless($m->is_active) <span class="badge badge-muted">Nonaktif</span>@endunless<br>
              <small>{{ $m->category?->name ?? 'Tanpa kategori' }} · {{ $m->station_name }}@if(!$m->recipe) · <a href="{{ route('recipes.create', ['menu_id' => $m->id]) }}">belum ada resep</a>@endif</small></td>
            <td class="num">{{ rupiah($price) }}@if($s?->price !== null)<br><small>dasar {{ rupiah($m->price) }}</small>@endif</td>
            <td class="num">{{ $cost > 0 ? rupiah($cost) : '—' }}</td>
            <td class="num">@if($cost > 0 && $price > 0)<span class="badge {{ ($price - $cost) / $price < .4 ? 'badge-warn' : 'badge-ok' }}">{{ number_format(($price - $cost) / $price * 100, 0) }}%</span>@else — @endif</td>
            <td><label class="check"><input type="checkbox" x-model="avail" @change="api('{{ route('menus.branch', $m) }}', { is_available: avail }).catch(e => { alert(e.message); avail = !avail })"></label></td>
            <td><label class="check"><input type="checkbox" x-model="sold" @change="api('{{ route('menus.branch', $m) }}', { sold_out: sold }).catch(e => { alert(e.message); sold = !sold })"></label></td>
          </tr>
        @empty <tr><td colspan="6" class="empty">Belum ada menu.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-head"><h3>Kategori</h3></div>
    <div class="card-pad">
      @foreach($categories as $c)
        <form method="POST" action="{{ route('categories.update', $c) }}" class="row" style="margin-bottom:6px">
          @csrf @method('PUT')
          <input name="name" value="{{ $c->name }}" class="input-sm" style="flex:1" required>
          <input type="number" name="sort" value="{{ $c->sort }}" class="input-sm" style="width:60px" title="Urutan">
          <label class="check" title="Aktif"><input type="checkbox" name="is_active" value="1" @checked($c->is_active)></label>
          <button class="btn btn-sm">Simpan</button>
        </form>
      @endforeach
      <hr>
      <form method="POST" action="{{ route('categories.store') }}" class="row">@csrf<input name="name" placeholder="Kategori baru" required style="flex:1"><button class="btn btn-primary btn-sm">Tambah</button></form>
    </div>
  </div>
</div>
@endsection
