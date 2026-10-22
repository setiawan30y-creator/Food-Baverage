@extends('layouts.app')
@section('title', 'Profil Usaha')
@section('content')
<div class="page-head"><div><h1>Profil usaha</h1><p>Tampil di aplikasi pelanggan, QR menu, POS, nota, dan laporan.</p></div></div>
<form method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" class="grid g-side" x-data="{ preview: @js($profile->logoUrl()) }">
  @csrf
  <div class="card card-pad">
    <div class="grid g2" style="gap:0 16px">
      <div class="field"><label>Nama usaha / restoran</label><input name="name" value="{{ old('name', $profile->name) }}" required></div>
      <div class="field"><label>Identitas usaha (NIB/NPWP, opsional)</label><input name="legal_id" value="{{ old('legal_id', $profile->legal_id) }}"></div>
      <div class="field"><label>Telepon</label><input name="phone" value="{{ old('phone', $profile->phone) }}"></div>
      <div class="field"><label>WhatsApp</label><input name="whatsapp" value="{{ old('whatsapp', $profile->whatsapp) }}"></div>
      <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email', $profile->email) }}"></div>
    </div>
    <div class="field"><label>Alamat</label><input name="address" value="{{ old('address', $profile->address) }}"></div>
    <div class="field"><label>Teks atas nota</label><input name="receipt_header" value="{{ old('receipt_header', $profile->receipt_header) }}"></div>
    <div class="field"><label>Teks bawah nota</label><input name="receipt_footer" value="{{ old('receipt_footer', $profile->receipt_footer) }}"></div>
    <button class="btn btn-primary">Simpan</button>
  </div>
  <div class="card card-pad">
    <h3 style="margin-bottom:10px">Logo</h3>
    <div style="width:140px;height:140px;border:1px dashed var(--border);border-radius:16px;display:grid;place-items:center;overflow:hidden;margin-bottom:12px;background:var(--subtle)">
      <img x-show="preview" :src="preview" alt="Pratinjau logo" style="width:100%;height:100%;object-fit:contain"><span x-show="!preview" class="muted">Belum ada logo</span>
    </div>
    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : preview">
    <small class="muted" style="display:block;margin-top:6px">PNG/JPG/WebP, maks 1 MB, 64–2000 px. Klik Simpan untuk menerapkan.</small>
  </div>
</form>
@if($profile->logo_path)
  <form method="POST" action="{{ route('settings.company.logo.delete') }}" onsubmit="return confirm('Hapus logo?')" style="margin-top:12px">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Hapus logo</button></form>
@endif
@endsection
