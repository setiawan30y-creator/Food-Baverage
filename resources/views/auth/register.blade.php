<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="author" content="@2026-Alifiandra">
<title>Daftar usaha · {{ config('fnb.product_name') }}</title>
@include('partials.theme-head')
</head>
<body>
<div style="max-width:980px;margin:0 auto;padding:28px 16px">
  <div style="text-align:center;margin-bottom:22px">
    <span class="logo-fallback" style="width:52px;height:52px;border-radius:14px;margin:0 auto 12px;font-size:22px">A</span>
    <h1>Daftarkan usaha Anda</h1>
    <p class="muted">Coba gratis {{ $trialDays }} hari, tanpa kartu kredit. Kasir, dapur, stok & resep untuk usaha makanan dan minuman.</p>
  </div>
  @include('partials.flash')
  <form method="POST" action="{{ url('/daftar') }}" x-data="{ plan: @js(old('plan', $plan)) }">
    @csrf
    <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
    <div class="grid g3" style="margin-bottom:18px">
      @foreach($plans as $k => $p)
        <label class="card card-pad" style="cursor:pointer" :style="plan === '{{ $k }}' ? 'outline:2px solid var(--primary)' : ''">
          <input type="radio" name="plan" value="{{ $k }}" x-model="plan" style="position:absolute;opacity:0">
          <div class="row between"><h3>{{ $p['label'] }}</h3>@if($k === 'pro')<span class="badge badge-primary">Populer</span>@endif</div>
          <div class="value" style="font-size:22px;font-weight:800;margin:6px 0">{{ rupiah($p['price']) }}<small class="muted" style="font-size:13px;font-weight:500">/bulan</small></div>
          <p class="muted" style="margin:0 0 8px">{{ $p['tagline'] }}</p>
          <small>{{ $p['max_branches'] }} cabang · {{ $p['max_users'] }} pengguna</small>
        </label>
      @endforeach
    </div>
    <div class="card card-pad">
      <div class="grid g2" style="gap:0 16px">
        <div class="field"><label for="business_name">Nama usaha</label><input id="business_name" name="business_name" value="{{ old('business_name') }}" required placeholder="Nama usaha Anda"></div>
        <div class="field"><label for="business_type">Jenis usaha</label><select id="business_type" name="business_type">@foreach($types as $k => $l)<option value="{{ $k }}" @selected(old('business_type') === $k)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="owner_name">Nama pemilik</label><input id="owner_name" name="owner_name" value="{{ old('owner_name') }}" required placeholder="Nama lengkap" autocomplete="name"></div>
        <div class="field"><label for="phone">No. WhatsApp</label><input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required placeholder="08xxxxxxxxxx" autocomplete="tel"></div>
        <div class="field"><label for="email">Email (untuk login)</label><input id="email" type="email" name="email" value="{{ old('email') }}" required placeholder="nama@usaha.com" autocomplete="email"></div>
        <div class="field"><label for="branch_name">Nama outlet / cabang pertama</label><input id="branch_name" name="branch_name" value="{{ old('branch_name', 'Cabang Utama') }}"></div>
        <div class="field"><label for="password">Kata sandi</label><input id="password" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
        <div class="field"><label for="password_confirmation">Ulangi kata sandi</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div>
      </div>
      <div class="field"><label for="branch_address">Alamat outlet (opsional)</label><input id="branch_address" name="branch_address" value="{{ old('branch_address') }}"></div>
      <div class="field"><label class="check"><input type="checkbox" name="demo_menu" value="1" @checked(old('demo_menu'))> Isi contoh menu snack agar bisa langsung mencoba kasir (bisa dinonaktifkan nanti)</label></div>
      <div class="field"><label class="check"><input type="checkbox" name="agree" value="1" required> Saya menyetujui syarat layanan & kebijakan privasi</label></div>
      <button class="btn btn-primary btn-lg btn-block">Mulai trial gratis</button>
      <p class="muted" style="text-align:center;margin-top:12px">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
    </div>
  </form>
  <p class="muted" style="text-align:center;font-size:12px;margin-top:16px">{{ config('fnb.signature') }}</p>
</div>
</body>
</html>
