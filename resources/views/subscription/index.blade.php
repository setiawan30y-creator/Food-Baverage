@extends('layouts.app')
@section('title', 'Langganan')
@section('content')
@php $st = $tenant->effectiveStatus(); $days = $tenant->daysLeft(); @endphp
<div class="page-head"><div><h1>Langganan</h1><p>Paket, masa aktif & tagihan {{ $tenant->name }}.</p></div></div>
<div class="grid g4" style="margin-bottom:16px">
  <div class="card stat"><div class="label">Paket</div><div class="value" style="font-size:22px">{{ $tenant->planLabel() }}</div><div class="hint">{{ rupiah($tenant->planConfig()['price']) }}/bulan</div></div>
  <div class="card stat"><div class="label">Status</div><div class="value" style="font-size:22px">@include('partials.tenant-status', ['t' => $tenant])</div>
    <div class="hint">{{ $tenant->accessEndsAt() ? ($st === 'trial' ? 'Trial s/d ' : 'Aktif s/d ').$tenant->accessEndsAt()->translatedFormat('d M Y') : '' }}</div></div>
  <div class="card stat"><div class="label">Sisa hari</div><div class="value" style="font-size:22px">{{ $days === null ? '—' : max(0, $days) }}</div><div class="hint">@if($st === 'past_due')Masa tenggang — segera perpanjang @endif</div></div>
  <div class="card stat"><div class="label">Pemakaian</div><div class="value" style="font-size:22px">{{ $usage['branches'] }}/{{ $tenant->max_branches }} <small style="font-size:13px">cabang</small></div><div class="hint">{{ $usage['users'] }}/{{ $tenant->max_users }} pengguna</div></div>
</div>
@if($open)
  <div class="alert alert-info">Ada tagihan {{ $open->number }} ({{ $open->statusLabel() }}) sebesar <b>{{ rupiah($open->amount) }}</b>. <a href="{{ route('subscription.invoice', $open) }}">Lihat & bayar →</a></div>
@endif
<form method="POST" action="{{ route('subscription.store') }}" x-data="{ plan: @js($tenant->plan), months: 1, prices: @js(collect($plans)->map(fn ($p) => $p['price'])) }">
  @csrf
  <h2 style="margin-bottom:10px">Perpanjang / ganti paket</h2>
  <div class="grid g3" style="margin-bottom:14px">
    @foreach($plans as $k => $p)
      <label class="card card-pad" style="cursor:pointer" :style="plan === '{{ $k }}' ? 'outline:2px solid var(--primary)' : ''">
        <input type="radio" name="plan" value="{{ $k }}" x-model="plan" style="position:absolute;opacity:0">
        <div class="row between"><h3>{{ $p['label'] }}</h3>@if($tenant->plan === $k)<span class="badge badge-primary">Paket saat ini</span>@endif</div>
        <div style="font-size:20px;font-weight:800;margin:6px 0">{{ rupiah($p['price']) }}<small class="muted" style="font-size:13px;font-weight:500">/bulan</small></div>
        <p class="muted" style="margin:0 0 8px">{{ $p['tagline'] }}</p>
        <small>{{ $p['max_branches'] }} cabang · {{ $p['max_users'] }} pengguna</small>
        <ul style="margin:8px 0 0 16px;padding:0;font-size:13px">@foreach($p['modules'] as $m)<li>{{ config('fnb.modules.'.$m) }}</li>@endforeach</ul>
      </label>
    @endforeach
  </div>
  <div class="card card-pad row between">
    <div class="row"><label for="months" style="margin:0">Durasi</label>
      <select id="months" name="months" x-model.number="months" style="width:auto">@foreach(config('fnb.billing_months') as $m => $l)<option value="{{ $m }}">{{ $l }}</option>@endforeach</select></div>
    <div class="row"><span class="muted">Total</span><b style="font-size:20px" x-text="rupiah(prices[plan] * (months >= 12 ? months - 2 * Math.floor(months / 12) : months))"></b>
      <button class="btn btn-primary">Buat tagihan</button></div>
  </div>
</form>
<div class="card" style="margin-top:16px">
  <div class="card-head"><h3>Riwayat tagihan</h3></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Nomor</th><th>Paket</th><th>Jumlah</th><th>Status</th><th>Periode</th><th>Aksi</th></tr></thead><tbody>
    @forelse($invoices as $i)
      <tr><td>{{ $i->number }}<br><small>{{ $i->created_at->format('d/m/Y') }}</small></td><td>{{ config('fnb.plans.'.$i->plan.'.label') }} · {{ $i->months }} bln</td>
        <td class="num">{{ rupiah($i->amount) }}</td><td>@include('partials.invoice-status', ['i' => $i])</td>
        <td>{{ $i->period_start ? $i->period_start->format('d/m/y').' – '.$i->period_end->format('d/m/y') : '—' }}</td>
        <td><a href="{{ route('subscription.invoice', $i) }}" class="btn btn-sm">{{ $i->isOpen() ? 'Bayar' : 'Detail' }}</a></td></tr>
    @empty <tr><td colspan="6" class="empty">Belum ada tagihan.</td></tr>
    @endforelse
  </tbody></table></div>
</div>
@endsection
