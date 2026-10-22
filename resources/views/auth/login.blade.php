<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="author" content="@2026-Alifiandra">
<title>Masuk · {{ config('fnb.product_name') }}</title>
@include('partials.theme-head')
</head>
<body>
<div style="min-height:100vh;display:grid;place-items:center;padding:16px">
  <div style="width:100%;max-width:400px">
    <div style="text-align:center;margin-bottom:20px">
      <span class="logo-fallback" style="width:52px;height:52px;border-radius:14px;margin:0 auto 12px;font-size:22px">A</span>
      <h1>Masuk ke akun usaha</h1>
      <p class="muted">{{ config('fnb.product_name') }}</p>
    </div>
    <div class="card card-pad">
      @include('partials.flash')
      <form method="POST" action="{{ url('/login') }}">
        @csrf
        <div class="field"><label for="email">Email</label><input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="nama@usaha.com" required autofocus autocomplete="username"></div>
        <div class="field"><label for="password">Kata sandi</label><input id="password" type="password" name="password" required autocomplete="current-password"></div>
        <div class="field"><label class="check"><input type="checkbox" name="remember" value="1"> Ingat saya di perangkat ini</label></div>
        <button class="btn btn-primary btn-block btn-lg">Masuk</button>
      </form>
      @if(\App\Models\PlatformSetting::get('registration_open') === '1')
        <p class="muted" style="text-align:center;margin:14px 0 0">Belum punya akun? <a href="{{ route('register') }}">Daftarkan usaha — gratis {{ \App\Models\PlatformSetting::get('trial_days') }} hari</a></p>
      @endif
    </div>
    <p class="muted" style="text-align:center;font-size:12px;margin-top:16px">{{ config('fnb.signature') }}</p>
  </div>
</div>
</body>
</html>
