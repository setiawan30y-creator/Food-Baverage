<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="author" content="@2026-Alifiandra">
<title>@yield('title', 'Dasbor') · {{ $companyProfile->name ?? config('fnb.product_name') }}</title>
@include('partials.theme-head')
@stack('head')
</head>
<body>
@php
  $u = auth()->user();
  $nav = [
    'Operasional' => [
      ['dashboard', 'Dasbor', 'home', 'dashboard', 'dashboard'],
      ['pos.index', 'Kasir (POS)', 'pos', 'pos', 'pos.*'],
      ['orders.index', 'Pesanan', 'receipt', 'orders', 'orders.*'],
      ['tables.index', 'Meja & QR', 'table', 'tables', 'tables.*'],
      ['kitchen.index', 'Dapur (KDS)', 'fire', 'kitchen', 'kitchen.*'],
      ['cashier.index', 'Shift & Closing', 'cash', 'closing', 'cashier.*'],
      ['closings.index', 'History Closing', 'archive', 'closing', 'closings.*'],
    ],
    'Produk & Stok' => [
      ['menus.index', 'Menu', 'menu', 'menu', 'menus.*'],
      ['recipes.index', 'Resep & HPP', 'flask', 'recipes', 'recipes.*'],
      ['production.index', 'Produksi Racikan', 'flask', 'production', 'production.*'],
      ['inventory.stock', 'Stok Bahan', 'box', 'inventory', 'inventory.*'],
      ['purchases.index', 'Pembelian', 'truck', 'purchasing', 'purchases.*'],
    ],
    'SDM' => [
      ['employees.index', 'Karyawan', 'users', 'employees', 'employees.*'],
      ['schedule.index', 'Jadwal Shift', 'calendar', 'schedule', 'schedule.*'],
      ['attendance.index', 'Rekap Absensi', 'clock', 'attendance.manage', 'attendance.index'],
      ['attendance.me', 'Absen Saya', 'pin', 'attendance.self', 'attendance.me'],
      ['leaves.index', 'Izin / Cuti / Lembur', 'calendar', 'attendance.self', 'leaves.*'],
      ['payroll.index', 'Payroll', 'wallet', 'payroll', 'payroll.index|payroll.show'],
      ['payroll.mine', 'Slip Gaji Saya', 'wallet', 'attendance.self', 'payroll.mine'],
    ],
    'Analisis' => [
      ['reports.sales', 'Laporan', 'chart', 'reports', 'reports.*'],
      ['customers.index', 'Pelanggan', 'user', 'orders', 'customers.*'],
      ['audit.index', 'Log Audit', 'shield', 'audit', 'audit.*'],
    ],
    'Pengaturan' => [
      ['settings.company', 'Profil Usaha', 'settings', 'settings', 'settings.company'],
      ['settings.theme', 'Tema', 'sun', 'settings', 'settings.theme'],
      ['settings.branches', 'Cabang', 'pin', 'settings', 'settings.branches'],
      ['settings.users', 'Pengguna', 'users', 'settings', 'settings.users'],
    ],
  ];
  $branches = $u->branch_id ? collect([$currentBranch]) : \App\Models\Branch::where('is_active', true)->orderBy('name')->get();
@endphp
<div class="shell" x-data="{ side: false }">
  <aside class="sidebar" :class="{ open: side }" @click.outside="side = false">
    <div class="brand">
      @include('partials.brand')
      <div><b>{{ $companyProfile->name ?? $currentTenant->name }}</b><small class="muted">F&B OS</small></div>
    </div>
    <nav class="nav" aria-label="Navigasi utama">
      @foreach ($nav as $group => $links)
        @php $visible = collect($links)->filter(fn ($l) => $u->hasAbility($l[3])); @endphp
        @if ($visible->isNotEmpty())
          <div class="nav-group">{{ $group }}</div>
          @foreach ($visible as [$route, $label, $icon, $ability, $pattern])
            <a href="{{ route($route) }}" class="{{ request()->routeIs(...explode('|', $pattern)) ? 'active' : '' }}"><x-icon :name="$icon"/> {{ $label }}</a>
          @endforeach
        @endif
      @endforeach
    </nav>
  </aside>

  <div class="main">
    <header class="topbar">
      <button class="btn btn-ghost btn-sm menu-toggle" @click.stop="side = !side" aria-label="Buka menu"><x-icon name="bars" style="width:20px;height:20px"/></button>
      @if ($branches->count() > 1)
        <form method="POST" action="{{ route('branch.switch') }}" class="row">
          @csrf
          <select name="branch_id" onchange="this.form.submit()" class="input-sm" aria-label="Cabang aktif" style="width:auto">
            @foreach ($branches as $b)<option value="{{ $b->id }}" @selected($b->id === $currentBranch->id)>{{ $b->name }}</option>@endforeach
          </select>
        </form>
      @else
        <span class="badge badge-primary"><x-icon name="pin" style="width:13px;height:13px;margin-right:4px"/>{{ $currentBranch->name }}</span>
      @endif
      <div class="spacer"></div>
      <form method="POST" action="{{ route('me.theme') }}" x-data>
        @csrf
        <input type="hidden" name="theme_mode" :value="document.documentElement.dataset.mode === 'dark' ? 'light' : 'dark'">
        <button class="btn btn-ghost btn-sm" title="Ganti mode terang/gelap" aria-label="Ganti mode terang/gelap"><x-icon name="moon" style="width:18px;height:18px"/></button>
      </form>
      <span class="hide-sm" style="text-align:right;line-height:1.2"><b>{{ $u->name }}</b><br><small>{{ $u->roleLabel() }}</small></span>
      <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-ghost btn-sm" title="Keluar" aria-label="Keluar"><x-icon name="logout" style="width:18px;height:18px"/></button></form>
    </header>

    <main class="content">
      @include('partials.flash')
      @yield('content')
    </main>
    <div class="footer-sign">{{ config('fnb.product_name') }} v{{ config('fnb.version') }} · {{ config('fnb.signature') }}</div>
  </div>
</div>
<div id="net-status" class="alert alert-warn no-print" style="display:none;position:fixed;bottom:12px;left:50%;transform:translateX(-50%);z-index:90;margin:0">Koneksi terputus. Transaksi yang membutuhkan server tidak dapat dikirim sampai koneksi pulih.</div>
@stack('scripts')
</body>
</html>
