@extends('layouts.app')
@section('title', 'Stok Bahan')
@section('content')
<div class="page-head"><div><h1>Stok bahan</h1><p>Saldo dihitung dari ledger pergerakan stok cabang {{ $currentBranch->name }}.</p></div>
  <div class="card stat" style="padding:10px 16px"><div class="label">Nilai persediaan</div><div class="value" style="font-size:20px">{{ rupiah($totalValue) }}</div></div></div>
@include('inventory._tabs')
<div class="grid g-side">
  <div class="card table-wrap">
    <table class="table">
      <thead><tr><th>Bahan</th><th>Jenis</th><th class="num">Saldo</th><th class="num">HPP / satuan</th><th class="num">Nilai</th><th></th></tr></thead>
      <tbody>
      @foreach($rows as $r)
        <tr>
          <td><b>{{ $r['item']->name }}</b><br><small>{{ $r['item']->category }}</small></td>
          <td>{!! $r['item']->type === 'sub_recipe' ? '<span class="badge badge-primary">Racikan</span>' : '<span class="badge badge-muted">Bahan baku</span>' !!}</td>
          <td class="num">@if($r['low'])<span class="badge badge-danger">{{ qty($r['balance']) }}</span>@else{{ qty($r['balance']) }}@endif <small>{{ $r['item']->base_unit }}</small></td>
          <td class="num">{{ rupiah($r['unit_cost'] < 1 ? 0 : $r['unit_cost']) }}@if($r['unit_cost'] > 0 && $r['unit_cost'] < 100)<br><small>{{ number_format($r['unit_cost'], 2, ',', '.') }}</small>@endif</td>
          <td class="num">{{ rupiah($r['value']) }}</td>
          <td><a href="{{ route('inventory.ledger', $r['item']) }}" class="btn btn-sm">Kartu stok</a></td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
  @if(auth()->user()->hasAbility('inventory.approve'))
  <div class="card card-pad">
    <h3 style="margin-bottom:10px">Penyesuaian stok</h3>
    <form method="POST" action="{{ route('inventory.adjust') }}">
      @csrf
      <div class="field"><label>Bahan</label><select name="inventory_item_id" required>@foreach($rows as $r)<option value="{{ $r['item']->id }}">{{ $r['item']->name }} ({{ $r['item']->base_unit }})</option>@endforeach</select></div>
      <div class="grid g2" style="gap:0 10px">
        <div class="field"><label>Arah</label><select name="direction"><option value="in">Tambah (+)</option><option value="out">Kurang (−)</option></select></div>
        <div class="field"><label>Jumlah (satuan dasar)</label><input type="number" step="any" name="qty" min="0" required></div>
      </div>
      <div class="field"><label>Alasan (wajib)</label><input name="reason" required placeholder="mis. koreksi salah input penerimaan"></div>
      <button class="btn btn-primary btn-block">Catat penyesuaian</button>
      <small class="muted" style="display:block;margin-top:8px">Untuk barang rusak/basi gunakan menu Waste agar tercatat sebagai kerugian.</small>
    </form>
  </div>
  @endif
</div>
@endsection
