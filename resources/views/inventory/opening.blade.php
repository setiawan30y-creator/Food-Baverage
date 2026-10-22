@extends('layouts.app')
@section('title', 'Saldo Awal Persediaan')
@section('content')
<div class="page-head"><div><h1>Setup awal: saldo persediaan</h1><p>Isi stok fisik & harga per satuan dasar saat mulai memakai aplikasi. Sekali per bahan per cabang; koreksi selanjutnya via penyesuaian / stock opname.</p></div></div>
@include('inventory._tabs')
<form method="POST" action="{{ route('inventory.opening.store') }}" class="card table-wrap">
  @csrf
  <table class="table">
    <thead><tr><th>Bahan</th><th>Satuan dasar</th><th class="num">Jumlah</th><th class="num">Harga / satuan dasar</th></tr></thead>
    <tbody>
    @foreach($items as $i)
      <tr><td>{{ $i->name }} @if($i->type==='sub_recipe')<span class="badge badge-primary">Racikan</span>@endif</td><td>{{ $i->base_unit }}</td>
        @if($hasOpening->contains($i->id))<td colspan="2" class="num"><span class="badge badge-ok">Sudah ada saldo awal</span></td>
        @else<td class="num"><input type="number" step="any" min="0" name="lines[{{ $i->id }}][qty]" class="input-sm" style="width:130px"></td><td class="num"><input type="number" step="any" min="0" name="lines[{{ $i->id }}][unit_cost]" class="input-sm" style="width:130px"></td>@endif
      </tr>
    @endforeach
    </tbody>
  </table>
  @if(auth()->user()->hasAbility('inventory.approve'))<div class="card-pad"><button class="btn btn-primary" onclick="return confirm('Saldo awal akan difinalkan. Lanjutkan?')">Simpan & finalkan saldo awal</button></div>
  @else<div class="card-pad muted">Finalisasi saldo awal memerlukan manajer.</div>@endif
</form>
@endsection
