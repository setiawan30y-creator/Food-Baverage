@extends('layouts.app')
@section('title', $recipe->exists ? $recipe->name : 'Resep baru')
@section('content')
@php
  $isSub = old('type', $recipe->type) === 'sub_recipe';
  $itemData = $items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'unit' => $i->base_unit, 'sub' => $i->type === 'sub_recipe', 'cost' => app(\App\Domain\Production\CostingService::class)->itemUnitCost($i)]);
  $oldLines = old('lines', $lines ?: [['inventory_item_id' => '', 'qty' => '']]);
@endphp
<div class="page-head"><div><a href="{{ route('recipes.index') }}">← Resep</a><h1>{{ $recipe->exists ? $recipe->name.' · v'.$recipe->version : ($isSub ? 'Sub-recipe baru' : 'Resep menu baru') }}</h1>
  @if($recipe->exists)<p>Menyimpan perubahan membuat <b>versi baru</b>. HPP transaksi lama tetap memakai snapshot saat itu.</p>@endif</div></div>
<div class="grid g-side">
<form method="POST" action="{{ $recipe->exists ? route('recipes.update', $recipe) : route('recipes.store') }}" class="card" x-data="recipe()">
  @csrf @if($recipe->exists) @method('PUT') @endif
  <input type="hidden" name="type" value="{{ $isSub ? 'sub_recipe' : 'menu' }}">
  <div class="card-pad grid g2" style="gap:0 16px">
    @if($isSub)
      <div class="field"><label>Bahan hasil (sub-recipe)</label><select name="output_item_id" required @change="if(!name) name=$event.target.selectedOptions[0].text">
        <option value="">Pilih bahan racikan</option>@foreach($subItems as $s)<option value="{{ $s->id }}" @selected(old('output_item_id', $recipe->output_item_id) == $s->id)>{{ $s->name }}</option>@endforeach</select>
        <small>Belum ada? <a href="{{ route('inventory.items.create') }}">Buat bahan jenis "Racikan"</a>.</small></div>
    @else
      <div class="field"><label>Menu</label><select name="menu_id" required @change="if(!name) name=$event.target.selectedOptions[0].text">
        <option value="">Pilih menu</option>@foreach($menus as $m)<option value="{{ $m->id }}" @selected(old('menu_id', $recipe->menu_id) == $m->id)>{{ $m->name }}</option>@endforeach</select></div>
    @endif
    <div class="field"><label>Nama resep</label><input name="name" x-model="name" required></div>
    <div class="field"><label>Hasil per resep (yield)</label><input type="number" step="any" min="0" name="yield_qty" x-model.number="yieldQty" required>
      <small>{{ $isSub ? 'Jumlah hasil aktual dalam satuan dasar bahan racikan (mis. 1000 gram sambal).' : 'Jumlah porsi yang dihasilkan resep ini (biasanya 1).' }}</small></div>
    <div class="field"><label>Perkiraan susut (%)</label><input type="number" step="any" min="0" max="100" name="expected_waste_percent" value="{{ old('expected_waste_percent', $recipe->expected_waste_percent ?? 0) }}"></div>
  </div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Bahan</th><th class="num">Takaran</th><th>Satuan</th><th class="num">Biaya</th><th></th></tr></thead>
    <tbody>
      <template x-for="(l, i) in lines" :key="i">
        <tr>
          <td><select :name="'lines['+i+'][inventory_item_id]'" x-model.number="l.inventory_item_id" required style="min-width:200px"><option value="">Pilih bahan</option>
            <template x-for="it in items" :key="it.id"><option :value="it.id" x-text="it.name + (it.sub ? ' (racikan)' : '')" :selected="it.id===l.inventory_item_id"></option></template></select></td>
          <td class="num"><input type="number" step="any" min="0" :name="'lines['+i+'][qty]'" x-model.number="l.qty" class="input-sm" style="width:110px" required></td>
          <td x-text="item(l).unit || ''"></td>
          <td class="num" x-text="rupiah((l.qty||0) * (item(l).cost||0))"></td>
          <td><button type="button" class="btn btn-ghost btn-sm" @click="lines.splice(i,1)" x-show="lines.length>1">✕</button></td>
        </tr>
      </template>
    </tbody>
    <tfoot>
      <tr><td colspan="3"><button type="button" class="btn btn-sm" @click="lines.push({inventory_item_id:'', qty:''})">+ Bahan</button></td><td class="num" x-text="rupiah(total)"></td><td></td></tr>
      <tr><td colspan="3">{{ $isSub ? 'HPP per satuan hasil' : 'HPP per porsi' }}</td><td class="num" x-text="(perUnit < 100 ? perUnit.toLocaleString('id-ID', {maximumFractionDigits: 2}) : rupiah(perUnit))"></td><td></td></tr>
    </tfoot>
  </table></div>
  <div class="card-pad"><div class="field"><label>Catatan / cara membuat</label><input name="note" value="{{ old('note', $recipe->note) }}"></div>
    <button class="btn btn-primary">{{ $recipe->exists ? 'Simpan sebagai versi baru' : 'Simpan resep' }}</button></div>
</form>
<div class="stack">
  <div class="card card-pad">
    <h3>Cara kerja HPP</h3>
    <p class="muted" style="margin-top:6px">Biaya bahan baku = HPP rata-rata tertimbang dari pembelian. Biaya bahan racikan = HPP batch produksinya (yield aktual), atau HPP teoritis resepnya bila belum pernah diproduksi.</p>
    @if($isSub && $recipe->exists)<a href="{{ route('production.create', ['recipe_id' => $recipe->id]) }}" class="btn btn-accent">Produksi batch</a>@endif
  </div>
  @if(!empty($versions) && $versions->count() > 1)
  <div class="card"><div class="card-head"><h3>Versi</h3></div><table class="table"><tbody>
    @foreach($versions as $v)<tr><td><a href="{{ route('recipes.edit', $v) }}">v{{ $v->version }}</a></td><td>{{ $v->effective_from?->format('d/m/Y') }}</td><td>{!! $v->is_active ? '<span class="badge badge-ok">Aktif</span>' : '<span class="badge badge-muted">Arsip</span>' !!}</td></tr>@endforeach
  </tbody></table></div>
  @endif
</div>
</div>
@endsection
@push('scripts')
<script>
function recipe() {
  return {
    items: @js($itemData), lines: @js(array_values($oldLines)).map(l => ({ inventory_item_id: Number(l.inventory_item_id) || '', qty: Number(l.qty) || '' })),
    name: @js(old('name', $recipe->name ?? '')), yieldQty: {{ (float) old('yield_qty', $recipe->yield_qty ?? 1) }},
    item(l) { return this.items.find(i => i.id === Number(l.inventory_item_id)) || {}; },
    get total() { return this.lines.reduce((s, l) => s + (l.qty || 0) * (this.item(l).cost || 0), 0); },
    get perUnit() { return this.yieldQty > 0 ? this.total / this.yieldQty : 0; },
  };
}
</script>
@endpush
