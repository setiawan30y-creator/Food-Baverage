@extends('layouts.app')
@section('title', $item->exists ? $item->name : 'Bahan baru')
@section('content')
<div class="page-head"><div><a href="{{ route('inventory.items.index') }}">← Master bahan</a><h1>{{ $item->exists ? $item->name : 'Bahan baru' }}</h1></div></div>
<form method="POST" action="{{ $item->exists ? route('inventory.items.update', $item) : route('inventory.items.store') }}" class="card card-pad" style="max-width:760px"
      x-data="{ type: @js(old('type', $item->type)), conv: @js(old('conversions', $item->exists ? $item->conversions->map(fn ($c) => ['unit' => $c->unit, 'factor' => (float) $c->factor])->values() : [])) }">
  @csrf @if($item->exists) @method('PUT') @endif
  <div class="grid g2" style="gap:0 16px">
    <div class="field"><label>Nama bahan</label><input name="name" value="{{ old('name', $item->name) }}" required placeholder="Nama bahan"></div>
    <div class="field"><label>Kategori</label><input name="category" value="{{ old('category', $item->category) }}" placeholder="mis. Bumbu, Protein, Kemasan"></div>
    <div class="field"><label>Jenis</label>
      <select name="type" x-model="type"><option value="raw">Bahan baku (beli / langsung pakai)</option><option value="sub_recipe">Racikan hasil produksi (sub-recipe)</option></select>
      <small x-show="type==='sub_recipe'">Saus, sambal, bumbu tabur, marinasi, adonan, kaldu… Stoknya bertambah lewat Produksi; HPP dari resepnya.</small></div>
    <div class="field"><label>Satuan dasar</label>
      <input name="base_unit" value="{{ old('base_unit', $item->base_unit) }}" list="units" required><datalist id="units">@foreach(config('fnb.units') as $u)<option value="{{ $u }}">@endforeach</datalist>
      <small>Satuan terkecil untuk resep & stok (mis. gram, mililiter, pcs).</small></div>
    <div class="field"><label>Stok minimum</label><input type="number" step="any" name="min_stock" value="{{ old('min_stock', $item->min_stock) }}" min="0"></div>
    <div class="field"><label>SKU (opsional)</label><input name="sku" value="{{ old('sku', $item->sku) }}"></div>
  </div>
  <div class="field"><label>Konversi kemasan</label>
    <template x-for="(c, i) in conv" :key="i">
      <div class="row" style="margin-bottom:6px"><span>1</span>
        <input :name="'conversions[' + i + '][unit]'" x-model="c.unit" list="units" placeholder="karton / kg / pack" class="input-sm" style="width:160px">
        <span>=</span><input type="number" step="any" :name="'conversions[' + i + '][factor]'" x-model="c.factor" class="input-sm" style="width:120px">
        <span>{{ $item->base_unit ?: 'satuan dasar' }}</span><button type="button" class="btn btn-ghost btn-sm" @click="conv.splice(i,1)">✕</button></div>
    </template>
    <button type="button" class="btn btn-sm" @click="conv.push({unit:'', factor:''})">+ Tambah konversi</button>
    <small>Sesuaikan dengan isi aktual, mis. 1 karton = 24 botol.</small>
  </div>
  <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $item->is_active ?? true))> Aktif</label></div>
  <button class="btn btn-primary">Simpan</button>
</form>
@endsection
