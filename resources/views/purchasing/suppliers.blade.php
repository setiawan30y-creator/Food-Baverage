@extends('layouts.app')
@section('title', 'Supplier')
@section('content')
<div class="page-head"><div><h1>Supplier</h1></div></div>
@include('purchasing._tabs')
<div class="grid g-side">
  <div class="card table-wrap"><table class="table"><thead><tr><th>Nama</th><th>Kontak</th><th>Lead time</th><th>Aksi</th></tr></thead><tbody>
    @forelse($suppliers as $s)
      <tr x-data="{ e: false }">
        <td><b>{{ $s->name }}</b><br><small>{{ $s->address }}</small></td><td>{{ $s->phone }}</td><td>{{ $s->lead_time_days }} hari</td>
        <td><button class="btn btn-sm" @click="e=!e">Ubah</button>
          <form x-show="e" x-cloak method="POST" action="{{ route('suppliers.update', $s) }}" style="margin-top:8px;min-width:240px">@csrf @method('PUT')
            <input name="name" value="{{ $s->name }}" class="input-sm" required><input name="phone" value="{{ $s->phone }}" class="input-sm" placeholder="Telepon" style="margin-top:4px">
            <input name="address" value="{{ $s->address }}" class="input-sm" placeholder="Alamat" style="margin-top:4px"><input type="number" name="lead_time_days" value="{{ $s->lead_time_days }}" class="input-sm" style="margin-top:4px">
            <button class="btn btn-sm btn-primary" style="margin-top:4px">Simpan</button></form></td>
      </tr>
    @empty <tr><td colspan="4" class="empty">Belum ada supplier.</td></tr>
    @endforelse
  </tbody></table></div>
  <form method="POST" action="{{ route('suppliers.store') }}" class="card card-pad">@csrf
    <h3 style="margin-bottom:10px">Supplier baru</h3>
    <div class="field"><label>Nama</label><input name="name" required placeholder="Nama supplier"></div>
    <div class="field"><label>Telepon</label><input name="phone"></div>
    <div class="field"><label>Alamat</label><input name="address"></div>
    <div class="field"><label>Lead time (hari)</label><input type="number" name="lead_time_days" value="1" min="0"></div>
    <button class="btn btn-primary btn-block">Tambah</button>
  </form>
</div>
@endsection
