@extends('layouts.app')
@section('title', 'Meja & QR')
@section('content')
<div class="page-head">
  <div><h1>Meja & QR</h1><p>Setiap meja punya QR unik. Pelanggan memindai untuk melihat menu & memesan.</p></div>
  <div class="row"><a href="{{ route('tables.print') }}" target="_blank" class="btn"><x-icon name="print" style="width:16px;height:16px"/> Cetak semua QR</a></div>
</div>
<div class="grid g4" style="margin-bottom:16px">
  @forelse($tables as $t)
    @php $orders = $t->activeSession?->orders ?? collect(); $unpaid = $orders->where('payment_status', '!=', 'paid')->sum('total'); @endphp
    <div class="card card-pad" x-data="{ edit: false, move: false }">
      <div class="row between"><h3>{{ $t->name }}</h3>{!! $t->is_active ? status_badge($t->status) : '<span class="badge badge-muted">Nonaktif</span>' !!}</div>
      <small>{{ $t->area ?: 'Area umum' }} · {{ $t->capacity }} kursi</small>
      @if($t->activeSession)
        <div style="margin:10px 0" class="muted">Sesi sejak {{ $t->activeSession->opened_at->format('H:i') }} · {{ $orders->count() }} pesanan
          @if($unpaid > 0)<br><b style="color:var(--danger)">Belum bayar {{ rupiah($unpaid) }}</b>@endif</div>
        @foreach($orders as $o)<a href="{{ route('orders.show', $o) }}" class="badge badge-muted" style="margin:0 4px 4px 0">{{ $o->number }}</a>@endforeach
        <div class="row" style="margin-top:8px">
          <form method="POST" action="{{ route('tables.close', $t) }}" onsubmit="return confirm('Tutup sesi meja {{ $t->name }}?')">@csrf<button class="btn btn-sm btn-accent">Tutup sesi</button></form>
          <button class="btn btn-sm" @click="move=!move">Pindah meja</button>
        </div>
        <form method="POST" action="{{ route('tables.move', $t) }}" x-show="move" class="row" style="margin-top:8px">
          @csrf
          <select name="target_id" class="input-sm" style="width:auto">@foreach($tables->where('id', '!=', $t->id)->where('status', 'available')->where('is_active', true) as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select>
          <button class="btn btn-sm">Pindahkan</button>
        </form>
      @endif
      <div class="row" style="margin-top:10px">
        <a href="{{ route('qr.show', $t->qr_token) }}" target="_blank" class="btn btn-sm">Buka menu QR</a>
        <button class="btn btn-sm btn-ghost" @click="edit=!edit">Ubah</button>
      </div>
      <div x-show="edit" x-cloak style="margin-top:10px">
        <form method="POST" action="{{ route('tables.update', $t) }}">
          @csrf @method('PUT')
          <div class="grid g2" style="gap:6px"><input name="name" value="{{ $t->name }}" class="input-sm" required><input name="area" value="{{ $t->area }}" class="input-sm" placeholder="Area"></div>
          <div class="row" style="margin-top:6px"><input type="number" name="capacity" value="{{ $t->capacity }}" class="input-sm" style="width:80px" min="1"><label class="check"><input type="checkbox" name="is_active" value="1" @checked($t->is_active)> Aktif</label><button class="btn btn-sm btn-primary">Simpan</button></div>
        </form>
        <form method="POST" action="{{ route('tables.regenerate', $t) }}" onsubmit="return confirm('QR lama akan tidak berlaku. Lanjutkan?')" style="margin-top:6px">@csrf<button class="btn btn-sm btn-danger">Buat ulang QR</button></form>
      </div>
    </div>
  @empty
    <div class="card empty" style="grid-column:1/-1">Belum ada meja.</div>
  @endforelse
</div>
<div class="card card-pad" style="max-width:560px">
  <h3 style="margin-bottom:10px">Tambah meja</h3>
  <form method="POST" action="{{ route('tables.store') }}" class="row">
    @csrf
    <input name="name" placeholder="Nama meja (mis. Meja 9)" required style="flex:1">
    <input name="area" placeholder="Area" style="width:120px">
    <input type="number" name="capacity" value="4" min="1" style="width:80px" aria-label="Kapasitas">
    <button class="btn btn-primary">Tambah</button>
  </form>
</div>
@endsection
