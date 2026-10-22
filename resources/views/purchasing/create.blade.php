@extends('layouts.app')
@section('title', 'Pembelian baru')
@section('content')
<div class="page-head"><div><a href="{{ route('purchases.index') }}">← Pembelian</a><h1>Pembelian baru</h1></div></div>
@php $itemData = $items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'base' => $i->base_unit, 'units' => collect([$i->base_unit])->merge($i->conversions->pluck('unit'))->values()]); @endphp
<form method="POST" action="{{ route('purchases.store') }}" class="card" x-data="purchase()">
  @csrf
  <div class="card-pad grid g3" style="gap:0 16px">
    <div class="field"><label>Supplier</label><select name="supplier_id"><option value="">—</option>@foreach($suppliers as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
    <div class="field"><label>Tanggal</label><input type="date" name="date" value="{{ today()->toDateString() }}" required></div>
    <div class="field"><label>Catatan</label><input name="note" placeholder="No. faktur supplier, dll."></div>
  </div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Bahan</th><th class="num">Jumlah</th><th>Satuan</th><th class="num">Harga / satuan</th><th class="num">Subtotal</th><th></th></tr></thead>
    <tbody>
      <template x-for="(l, i) in lines" :key="i">
        <tr>
          <td><select :name="'lines['+i+'][inventory_item_id]'" x-model.number="l.item" @change="l.unit = item(l).base" required style="min-width:180px"><option value="">Pilih bahan</option><template x-for="it in items" :key="it.id"><option :value="it.id" x-text="it.name" :selected="it.id===l.item"></option></template></select></td>
          <td class="num"><input type="number" step="any" min="0" :name="'lines['+i+'][qty]'" x-model.number="l.qty" class="input-sm" style="width:100px" required></td>
          <td><select :name="'lines['+i+'][unit]'" x-model="l.unit" class="input-sm"><template x-for="u in (item(l).units || [])" :key="u"><option :value="u" x-text="u" :selected="u===l.unit"></option></template></select></td>
          <td class="num"><input type="number" step="any" min="0" :name="'lines['+i+'][price]'" x-model.number="l.price" class="input-sm" style="width:120px" required></td>
          <td class="num" x-text="rupiah((l.qty||0)*(l.price||0))"></td>
          <td><button type="button" class="btn btn-ghost btn-sm" @click="lines.splice(i,1)" x-show="lines.length>1">✕</button></td>
        </tr>
      </template>
    </tbody>
    <tfoot><tr><td colspan="4"><button type="button" class="btn btn-sm" @click="lines.push({item:'',qty:1,unit:'',price:0})">+ Baris</button></td><td class="num" x-text="rupiah(total)"></td><td></td></tr></tfoot>
  </table></div>
  <div class="card-pad row"><button name="action" value="draft" class="btn">Simpan draft</button><button name="action" value="receive" class="btn btn-primary">Simpan & terima barang</button></div>
</form>
@endsection
@push('scripts')
<script>
function purchase() {
  return {
    items: @js($itemData), lines: [{ item: '', qty: 1, unit: '', price: 0 }],
    item(l) { return this.items.find(i => i.id === Number(l.item)) || {}; },
    get total() { return this.lines.reduce((s, l) => s + (l.qty || 0) * (l.price || 0), 0); },
  };
}
</script>
@endpush
