@extends('layouts.app')
@section('title', 'Saldo Kas & Rekening')
@section('content')
{{-- @2026-Alifiandra --}}
<div class="page-head">
  <div><h1>Saldo Kas & Rekening</h1><p>Saldo bank, QRIS, e-wallet & kas — dari saldo awal, penjualan yang masuk, dan mutasi.</p></div>
  <a href="{{ route('finance.create') }}" class="btn btn-primary">+ Tambah rekening</a>
</div>
<form class="filters">
  <div class="field"><label>Dari</label><input type="date" name="from" value="{{ $from->toDateString() }}"></div>
  <div class="field"><label>Sampai</label><input type="date" name="to" value="{{ $to->toDateString() }}"></div>
  <button class="btn">Tampilkan</button>
  <a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 1]) }}">Ekspor Excel (CSV)</a>
</form>

@php
  $active = $rows->filter(fn ($r) => $r['account']->is_active);
  $nonCash = $byMethod->except('cash');
@endphp
<div class="grid g4" style="margin-bottom:16px">
  <div class="card stat"><div class="label">Total saldo semua rekening</div><div class="value">{{ rupiah($active->sum('end')) }}</div><div class="hint">per {{ $to->translatedFormat('d M Y') }}</div></div>
  <div class="card stat"><div class="label">Penjualan masuk rekening</div><div class="value">{{ rupiah($active->sum('sales')) }}</div><div class="hint">{{ $active->sum('sales_count') }} transaksi</div></div>
  <div class="card stat"><div class="label">Pembayaran digital & bank</div><div class="value">{{ $nonCash->sum('cnt') }}×</div><div class="hint">{{ rupiah($nonCash->sum('total')) }} (QRIS, transfer, e-wallet)</div></div>
  <div class="card stat"><div class="label">Pembayaran tunai</div><div class="value">{{ (int) ($byMethod['cash']->cnt ?? 0) }}×</div><div class="hint">{{ rupiah($byMethod['cash']->total ?? 0) }}</div></div>
</div>

<div class="card table-wrap" style="margin-bottom:16px">
  <div class="card-head"><h3>Saldo per rekening</h3></div>
  <table class="table">
    <thead><tr><th>Rekening</th><th>Jenis</th><th class="num">Saldo awal periode</th><th class="num">Transaksi</th><th class="num">Penjualan masuk</th><th class="num">Mutasi masuk</th><th class="num">Mutasi keluar</th><th class="num">Saldo akhir</th></tr></thead>
    <tbody>
    @forelse($rows as $r)
      @php $a = $r['account']; @endphp
      <tr @if(!$a->is_active) style="opacity:.55" @endif>
        <td><a href="{{ route('finance.show', [$a, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"><b>{{ $a->name }}</b></a>
          <br><small class="muted">{{ collect([$a->account_number, $a->branch?->name ?? 'semua cabang', $a->is_active ? null : 'nonaktif'])->filter()->implode(' · ') }}</small></td>
        <td>{{ $a->methodLabel() }}</td>
        <td class="num">{{ rupiah($r['start']) }}</td>
        <td class="num">{{ $r['sales_count'] }}</td>
        <td class="num">{{ rupiah($r['sales']) }}</td>
        <td class="num">{{ rupiah($r['in']) }}</td>
        <td class="num">{{ rupiah($r['out']) }}</td>
        <td class="num"><b>{{ rupiah($r['end']) }}</b></td>
      </tr>
    @empty
      <tr><td colspan="8" class="empty">Belum ada rekening. Tambahkan rekening bank, QRIS, atau e-wallet (OVO, GoPay, DANA, ShopeePay) beserta saldo awalnya.</td></tr>
    @endforelse
    </tbody>
  </table>
</div>

<div class="grid g-side">
  <div class="card table-wrap">
    <div class="card-head"><h3>Pembayaran per metode</h3></div>
    <table class="table">
      <thead><tr><th>Metode</th><th class="num">Transaksi</th><th class="num">Total</th><th class="num">Belum ditautkan ke rekening</th></tr></thead>
      <tbody>
      @forelse(config('fnb.payment_methods') as $k => $label)
        @php $m = $byMethod[$k] ?? null; @endphp
        <tr>
          <td><a href="{{ route('sales.history', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'method' => $k]) }}">{{ $label }}</a></td>
          <td class="num">{{ (int) ($m->cnt ?? 0) }}</td>
          <td class="num">{{ rupiah($m->total ?? 0) }}</td>
          <td class="num">@if(($m->unlinked_cnt ?? 0) > 0){{ (int) $m->unlinked_cnt }}× · {{ rupiah($m->unlinked) }}@else — @endif</td>
        </tr>
      @empty <tr><td colspan="4" class="empty">—</td></tr>
      @endforelse
      </tbody>
    </table>
    <p class="muted" style="padding:0 16px 14px;margin:0;font-size:13px">"Belum ditautkan" = pembayaran sebelum rekening dibuat, atau metode yang belum punya rekening.</p>
  </div>

  <div class="card card-pad">
    <h3 style="margin-bottom:10px">Pindah dana antar rekening</h3>
    @if($activeAccounts->count() >= 2)
    <form method="POST" action="{{ route('finance.transfer') }}">
      @csrf
      <div class="field"><label for="from_id">Dari</label><select id="from_id" name="from_id" required>@foreach($activeAccounts as $a)<option value="{{ $a->id }}">{{ $a->label() }}</option>@endforeach</select></div>
      <div class="field"><label for="to_id">Ke</label><select id="to_id" name="to_id" required>@foreach($activeAccounts as $a)<option value="{{ $a->id }}" @selected($loop->index === 1)>{{ $a->label() }}</option>@endforeach</select></div>
      <div class="field"><label for="tf_amount">Jumlah</label><input id="tf_amount" type="number" name="amount" min="1" step="1" required></div>
      <div class="field"><label for="tf_date">Tanggal</label><input id="tf_date" type="date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
      <div class="field"><label for="tf_note">Catatan</label><input id="tf_note" type="text" name="note" maxlength="150" placeholder="mis. tarik saldo e-wallet ke bank"></div>
      <button class="btn btn-primary btn-block">Catat pindah dana</button>
    </form>
    @else
      <p class="muted">Butuh minimal 2 rekening aktif (mis. saldo QRIS/e-wallet yang ditarik ke bank, atau setor kas ke bank).</p>
    @endif
  </div>
</div>
@endsection
