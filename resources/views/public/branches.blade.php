@extends('layouts.public')
@section('title', 'Pesan Ambil Sendiri')
@section('subtitle', 'Pesan dari rumah, ambil di outlet')
@section('content')
<h2 style="margin-bottom:12px">Pilih outlet</h2>
@forelse($branches as $b)
  <a href="{{ route('oa.menu', [$tenant->slug, $b->id]) }}" class="list-item" style="color:inherit;text-decoration:none">
    <div class="ph"><x-icon name="pin" style="width:30px;height:30px"/></div>
    <div><b>{{ $b->name }}</b><br><small>{{ $b->address }}</small><br>
      <small>{{ substr($b->open_time,0,5) }}–{{ substr($b->close_time,0,5) }}</small> {!! $b->isOpenAt(now()) ? '<span class="badge badge-ok">Buka</span>' : '<span class="badge badge-muted">Tutup — bisa pesan untuk slot berikutnya</span>' !!}</div>
  </a>
@empty
  <div class="card empty">Belum ada outlet yang menerima pesanan online.</div>
@endforelse
@endsection
