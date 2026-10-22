<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="author" content="@2026-Alifiandra">
<title>@yield('title') · {{ $companyProfile->name ?? ($currentTenant->name ?? config('fnb.product_name')) }}</title>
@include('partials.theme-head')
</head>
<body>
<div class="public-wrap">
  <div class="public-head">
    @include('partials.brand')
    <div style="flex:1"><h2>{{ $companyProfile->name ?? $currentTenant->name }}</h2>@hasSection('subtitle')<small>@yield('subtitle')</small>@endif</div>
    @if(request()->is('o/*'))<button type="button" class="btn btn-sm" data-pwa-install hidden>Pasang aplikasi</button>@endif
  </div>
  @include('partials.flash')
  @yield('content')
  <p class="muted" style="text-align:center;font-size:12px;margin-top:24px">Didukung {{ config('fnb.product_name') }} · {{ config('fnb.signature') }}</p>
</div>
<div id="net-status" class="alert alert-warn" style="display:none;position:fixed;top:12px;left:50%;transform:translateX(-50%);z-index:90;margin:0">Koneksi terputus. Pesanan belum bisa dikirim.</div>
@if(request()->is('o/*'))@include('partials.pwa-ios-hint')@endif
@stack('scripts')
</body>
</html>
