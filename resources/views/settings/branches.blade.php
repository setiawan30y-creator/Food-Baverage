@extends('layouts.app')
@section('title', 'Cabang')
@section('content')
<div class="page-head"><div><h1>Cabang</h1><p>Lokasi & radius absensi, jam buka, pajak/service, Order Ahead, stasiun dapur. Paket: maks {{ $currentTenant->max_branches }} cabang.</p></div></div>
@php $fields = function ($b) { return view('settings._branch-fields', ['b' => $b])->render(); }; @endphp
<div class="stack">
@foreach($branches as $b)
  <details class="card disclose" @if($loop->first) open @endif>
    <summary class="card-head" style="display:flex"><b>{{ $b->name }} <small class="muted">({{ $b->code }})</small></b>{!! $b->is_active ? status_badge('active') : status_badge('inactive') !!}</summary>
    <div class="card-pad">
      <form method="POST" action="{{ route('settings.branches.update', $b) }}">@csrf @method('PUT') {!! $fields($b) !!}<button class="btn btn-primary">Simpan cabang</button></form>
      <hr>
      <h3 style="margin-bottom:8px">Stasiun dapur</h3>
      <div class="row">
        @foreach($b->stations as $s)<form method="POST" action="{{ route('settings.stations.destroy', $s) }}" class="row" style="gap:4px">@csrf @method('DELETE')<span class="badge badge-muted">{{ $s->name }}</span><button class="btn btn-ghost btn-sm" aria-label="Hapus stasiun">✕</button></form>@endforeach
        <form method="POST" action="{{ route('settings.stations.store', $b) }}" class="row">@csrf<input name="name" placeholder="Stasiun baru (mis. Bakery)" class="input-sm" required><button class="btn btn-sm">Tambah</button></form>
      </div>
      <p class="muted" style="margin-top:8px">Tautan Order Ahead cabang: <a href="{{ route('oa.menu', [$currentTenant->slug, $b->id]) }}" target="_blank">{{ route('oa.menu', [$currentTenant->slug, $b->id]) }}</a></p>
    </div>
  </details>
@endforeach
  <details class="card disclose"><summary class="card-head" style="display:flex"><b>+ Tambah cabang</b></summary>
    <div class="card-pad"><form method="POST" action="{{ route('settings.branches.store') }}">@csrf {!! $fields(new \App\Models\Branch(['geofence_radius' => 100, 'open_time' => '08:00', 'close_time' => '21:00', 'tax_percent' => 0, 'service_percent' => 0, 'pickup_slot_minutes' => 15, 'pickup_capacity_per_slot' => 10, 'order_ahead_enabled' => true, 'weather_widget' => true, 'block_out_of_stock' => true, 'is_active' => true])) !!}<button class="btn btn-primary">Tambah cabang</button></form></div>
  </details>
</div>
@endsection
