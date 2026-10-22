<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="author" content="@2026-Alifiandra">
<title>@yield('title', 'Super Admin') · {{ config('fnb.product_name') }}</title>
@include('partials.theme-head')
</head>
<body>
<div class="shell" x-data="{ side: false }">
  <aside class="sidebar" :class="{ open: side }" @click.outside="side = false">
    <div class="brand"><span class="logo-fallback" style="background:#1C1917">A</span><div><b>{{ config('fnb.product_name') }}</b><small class="muted">Super Admin</small></div></div>
    <nav class="nav" aria-label="Navigasi super admin">
      <div class="nav-group">Platform</div>
      <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"><x-icon name="home"/> Dasbor</a>
      <a href="{{ route('admin.tenants') }}" class="{{ request()->routeIs('admin.tenants*') ? 'active' : '' }}"><x-icon name="users"/> Penyewa</a>
      <a href="{{ route('admin.invoices') }}" class="{{ request()->routeIs('admin.invoices*') ? 'active' : '' }}"><x-icon name="receipt"/> Tagihan</a>
      <a href="{{ route('admin.settings') }}" class="{{ request()->routeIs('admin.settings*') ? 'active' : '' }}"><x-icon name="settings"/> Pengaturan</a>
    </nav>
  </aside>
  <div class="main">
    <header class="topbar">
      <button class="btn btn-ghost btn-sm menu-toggle" @click.stop="side = !side" aria-label="Buka menu"><x-icon name="bars" style="width:20px;height:20px"/></button>
      <span class="badge" style="background:#1C1917;color:#fff">Super Admin</span>
      <div class="spacer"></div>
      <span class="hide-sm" style="text-align:right;line-height:1.2"><b>{{ auth()->user()->name }}</b><br><small>{{ auth()->user()->email }}</small></span>
      <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost btn-sm" title="Keluar" aria-label="Keluar"><x-icon name="logout" style="width:18px;height:18px"/></button></form>
    </header>
    <main class="content">
      @include('partials.flash')
      @yield('content')
    </main>
    <div class="footer-sign">{{ config('fnb.product_name') }} v{{ config('fnb.version') }} · {{ config('fnb.signature') }}</div>
  </div>
</div>
@stack('scripts')
</body>
</html>
