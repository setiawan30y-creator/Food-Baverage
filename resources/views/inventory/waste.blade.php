@extends('layouts.app')
@section('title', 'Waste / Susut')
@section('content')
<div class="page-head"><div><h1>Waste & susut</h1><p>Barang rusak, basi, kedaluwarsa, tumpah, gagal produksi. Nilai > {{ rupiah(config('fnb.approval.waste_value')) }} wajib disetujui manajer.</p></div></div>
@include('inventory._tabs')
<div class="grid g-side">
  <div class="stack">
    <div class="grid g3">
      @foreach(\App\Models\WasteRecord::REASONS as $k => $l)
        @if(($summary[$k] ?? 0) > 0)<div class="card stat"><div class="label">{{ $l }} (bulan ini)</div><div class="value" style="font-size:18px">{{ rupiah($summary[$k]) }}</div></div>@endif
      @endforeach
    </div>
    <div class="tabs" style="margin:0">
      <a href="{{ route('inventory.waste.index') }}" class="{{ !request('status') ? 'active' : '' }}">Semua</a>
      <a href="{{ route('inventory.waste.index', ['status' => 'pending']) }}" class="{{ request('status')==='pending' ? 'active' : '' }}">Menunggu</a>
      <a href="{{ route('inventory.waste.index', ['status' => 'approved']) }}" class="{{ request('status')==='approved' ? 'active' : '' }}">Disetujui</a>
    </div>
    <div class="card table-wrap">
      <table class="table">
        <thead><tr><th>Waktu</th><th>Bahan</th><th>Alasan</th><th class="num">Jumlah</th><th class="num">Nilai</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($records as $w)
          <tr><td>{{ $w->created_at->format('d/m H:i') }}<br><small>{{ $w->requester?->name }}</small></td><td>{{ $w->item->name }}</td>
            <td>{{ \App\Models\WasteRecord::REASONS[$w->reason] ?? $w->reason }}@if($w->note)<br><small>{{ $w->note }}</small>@endif</td>
            <td class="num">{{ qty($w->qty) }} {{ $w->item->base_unit }}</td><td class="num">{{ rupiah($w->total_cost) }}</td>
            <td>{!! status_badge($w->status) !!}@if($w->approver)<br><small>{{ $w->approver->name }}</small>@endif</td>
            <td>@if($w->status==='pending' && auth()->user()->hasAbility('inventory.approve'))
              <form method="POST" action="{{ route('inventory.waste.decide', $w) }}" class="row">@csrf
                <button name="decision" value="approve" class="btn btn-sm btn-accent">Setujui</button><button name="decision" value="reject" class="btn btn-sm btn-danger">Tolak</button></form>@endif</td></tr>
        @empty <tr><td colspan="7" class="empty">Belum ada catatan waste.</td></tr>
        @endforelse
        </tbody>
      </table>
      {{ $records->links() }}
    </div>
  </div>
  <div class="card card-pad">
    <h3 style="margin-bottom:10px">Catat waste</h3>
    <form method="POST" action="{{ route('inventory.waste.store') }}">
      @csrf
      <div class="field"><label>Bahan</label><select name="inventory_item_id" required>@foreach($items as $i)<option value="{{ $i->id }}">{{ $i->name }} ({{ $i->base_unit }})</option>@endforeach</select></div>
      <div class="field"><label>Jumlah (satuan dasar)</label><input type="number" step="any" name="qty" min="0" required></div>
      <div class="field"><label>Alasan</label><select name="reason" required>@foreach(\App\Models\WasteRecord::REASONS as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
      <div class="field"><label>Keterangan</label><input name="note" placeholder="opsional"></div>
      <button class="btn btn-primary btn-block">Simpan</button>
    </form>
  </div>
</div>
@endsection
