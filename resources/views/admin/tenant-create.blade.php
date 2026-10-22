@extends('layouts.admin')
@section('title', 'Penyewa baru')
@section('content')
<div class="page-head"><div><a href="{{ route('admin.tenants') }}">← Penyewa</a><h1>Penyewa baru</h1><p>Buat akun usaha secara manual (mis. pelanggan yang mendaftar lewat WhatsApp).</p></div></div>
<form method="POST" action="{{ route('admin.tenants.store') }}" class="card card-pad" style="max-width:820px">
  @csrf
  <div class="grid g2" style="gap:0 16px">
    <div class="field"><label>Nama usaha</label><input name="business_name" value="{{ old('business_name') }}" required placeholder="Nama usaha"></div>
    <div class="field"><label>Jenis usaha</label><select name="business_type">@foreach($types as $k => $l)<option value="{{ $k }}" @selected(old('business_type') === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label>Nama pemilik</label><input name="owner_name" value="{{ old('owner_name') }}" required placeholder="Nama lengkap"></div>
    <div class="field"><label>No. WhatsApp</label><input name="phone" value="{{ old('phone') }}" placeholder="08xxxxxxxxxx"></div>
    <div class="field"><label>Email login pemilik</label><input type="email" name="email" value="{{ old('email') }}" required placeholder="nama@usaha.com"></div>
    <div class="field"><label>Kata sandi awal</label><input type="text" name="password" value="{{ old('password', \Illuminate\Support\Str::password(10, symbols: false)) }}" required minlength="8"><small>Kirimkan ke pemilik & minta diganti.</small></div>
    <div class="field"><label>Paket</label><select name="plan">@foreach($plans as $k => $p)<option value="{{ $k }}" @selected(old('plan', 'pro') === $k)>{{ $p['label'] }} — {{ rupiah($p['price']) }}/bln</option>@endforeach</select></div>
    <div class="field"><label>Status awal</label><select name="status"><option value="trial">Trial</option><option value="active" @selected(old('status') === 'active')>Aktif (1 bulan)</option></select></div>
    <div class="field"><label>Nama cabang pertama</label><input name="branch_name" value="{{ old('branch_name', 'Cabang Utama') }}"></div>
    <div class="field"><label>Alamat cabang</label><input name="branch_address" value="{{ old('branch_address') }}"></div>
  </div>
  <div class="field"><label class="check"><input type="checkbox" name="demo_menu" value="1"> Isi contoh menu snack (bisa dihapus/dinonaktifkan nanti)</label></div>
  <button class="btn btn-primary">Buat penyewa</button>
</form>
@endsection
