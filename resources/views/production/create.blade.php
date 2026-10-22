@extends('layouts.app')
@section('title', 'Produksi '.$recipe->name)
@section('content')
<div class="page-head"><div><a href="{{ route('production.index') }}">← Produksi</a><h1>Produksi: {{ $recipe->name }}</h1><p>Hasil standar {{ qty($recipe->yield_qty) }} {{ $recipe->outputItem->base_unit }} per resep (v{{ $recipe->version }}).</p></div></div>
<form method="POST" action="{{ route('production.store') }}" class="card" x-data="{ mult: {{ (float) old('multiplier', 1) }}, actual: {{ (float) old('actual_yield', $recipe->yield_qty) }}, base: {{ (float) $recipe->yield_qty }}, touched: false }" style="max-width:860px">
  @csrf
  <input type="hidden" name="recipe_id" value="{{ $recipe->id }}">
  <div class="card-pad grid g3" style="gap:0 16px">
    <div class="field"><label>Pengali resep</label><input type="number" step="any" min="0.01" name="multiplier" x-model.number="mult" @input="if(!touched) actual = +(base*mult).toFixed(4)" required></div>
    <div class="field"><label>Hasil rencana</label><input type="text" readonly :value="(base*mult).toLocaleString('id-ID') + ' {{ $recipe->outputItem->base_unit }}'"></div>
    <div class="field"><label>Hasil aktual (ditimbang)</label><input type="number" step="any" min="0" name="actual_yield" x-model.number="actual" @input="touched=true" required></div>
  </div>
  <div class="card-pad" style="padding-top:0" x-show="Math.abs(actual - base*mult) > 0.0001"><div class="field"><label>Alasan selisih yield (wajib)</label><input name="variance_reason" value="{{ old('variance_reason') }}" placeholder="mis. susut penguapan, tumpah sebagian"></div></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Bahan</th><th class="num">Standar</th><th class="num">Pemakaian aktual</th><th class="num">Stok tersedia</th></tr></thead><tbody>
    @foreach($recipe->items as $l)
      <tr x-data="{ edited: false, v: {{ (float) $l->qty }} }" x-effect="if(!edited) v = +({{ (float) $l->qty }} * mult).toFixed(4)">
        <td>{{ $l->item->name }} @if($l->item->type==='sub_recipe')<span class="badge badge-primary">Racikan</span>@endif</td>
        <td class="num" x-text="({{ (float) $l->qty }} * mult).toLocaleString('id-ID') + ' {{ $l->item->base_unit }}'"></td>
        <td class="num"><input type="number" step="any" min="0" name="consumptions[{{ $l->inventory_item_id }}]" x-model.number="v" @input="edited=true" class="input-sm" style="width:120px"></td>
        @php $bal = $balances[$l->inventory_item_id] ?? 0; @endphp
        <td class="num"><span :class="v > {{ $bal }} ? 'badge badge-danger' : ''">{{ qty($bal) }} {{ $l->item->base_unit }}</span></td>
      </tr>
    @endforeach
    </tbody></table></div>
  <div class="card-pad"><button class="btn btn-primary">Catat batch produksi</button> <small class="muted">Pemakaian aktual mengurangi stok bahan; hasil aktual menambah stok racikan.</small></div>
</form>
@endsection
