@extends('layouts.app')
@section('title', $account->name)
@section('content')
{{-- @2026-Alifiandra --}}
<div class="page-head">
  <div><h1>{{ $account->name }}</h1><p>{{ $account->methodLabel() }}{{ $account->account_number ? ' · '.$account->account_number : '' }}{{ $account->holder ? ' · a.n. '.$account->holder : '' }} · saldo awal {{ rupiah($account->opening_balance) }} per {{ $account->opening_date->translatedFormat('d M Y') }}</p></div>
  <div class="row"><a href="{{ route('finance.index', request()->only('from', 'to')) }}" class="btn">← Semua rekening</a><a href="{{ route('finance.edit', $account) }}" class="btn">Ubah</a></div>
</div>
<form class="filters">
  <div class="field"><label>Dari</label><input type="date" name="from" value="{{ $from->toDateString() }}"></div>
  <div class="field"><label>Sampai</label><input type="date" name="to" value="{{ $to->toDateString() }}"></div>
  <button class="btn">Tampilkan</button>
  <a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 1]) }}">Ekspor Excel (CSV)</a>
</form>
@php $rows = $ledger['rows']; @endphp
<div class="grid g4" style="margin-bottom:16px">
  <div class="card stat"><div class="label">Saldo awal periode</div><div class="value">{{ rupiah($ledger['start']) }}</div><div class="hint">{{ $from->translatedFormat('d M Y') }}</div></div>
  <div class="card stat"><div class="label">Masuk</div><div class="value">{{ rupiah($rows->sum('in')) }}</div><div class="hint">{{ $rows->where('type', 'Penjualan')->count() }} pembayaran penjualan</div></div>
  <div class="card stat"><div class="label">Keluar</div><div class="value">{{ rupiah($rows->sum('out')) }}</div></div>
  <div class="card stat"><div class="label">Saldo akhir</div><div class="value">{{ rupiah($ledger['end']) }}</div><div class="hint">{{ $to->translatedFormat('d M Y') }}</div></div>
</div>
<div class="grid g-side">
  <div class="card table-wrap">
    <table class="table table-compact">
      <thead><tr><th>Waktu</th><th>Jenis</th><th>Keterangan</th><th class="num">Masuk</th><th class="num">Keluar</th><th class="num">Saldo</th></tr></thead>
      <tbody>
      @forelse($rows as $r)
        <tr>
          <td>{{ $r['at']->format('d/m/Y H:i') }}</td>
          <td>{{ $r['type'] }}</td>
          <td>@if($r['order_id'])<a href="{{ route('orders.show', $r['order_id']) }}">{{ $r['desc'] }}</a>@else{{ $r['desc'] }}@endif
            @if($r['ref'])<br><small class="muted">ref {{ $r['ref'] }}</small>@endif @if($r['user'])<br><small class="muted">{{ $r['user'] }}</small>@endif</td>
          <td class="num">{{ $r['in'] ? rupiah($r['in']) : '—' }}</td>
          <td class="num">{{ $r['out'] ? rupiah($r['out']) : '—' }}</td>
          <td class="num"><b>{{ rupiah($r['balance']) }}</b></td>
        </tr>
      @empty <tr><td colspan="6" class="empty">Tidak ada mutasi pada periode ini.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  <div class="card card-pad">
    <h3 style="margin-bottom:10px">Catat mutasi manual</h3>
    <form method="POST" action="{{ route('finance.mutation', $account) }}">
      @csrf
      <div class="field"><label for="direction">Arah</label><select id="direction" name="direction"><option value="out">Keluar (−)</option><option value="in">Masuk (+)</option></select></div>
      <div class="field"><label for="category">Jenis</label><select id="category" name="category">@foreach(\App\Models\AccountMutation::CATEGORIES as $k => $l)@continue($k === 'transfer')<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
      <div class="field"><label for="amount">Jumlah</label><input id="amount" type="number" name="amount" min="1" step="1" required></div>
      <div class="field"><label for="date">Tanggal</label><input id="date" type="date" name="date" value="{{ today()->toDateString() }}" max="{{ today()->toDateString() }}" required></div>
      <div class="field"><label for="note">Keterangan</label><input id="note" type="text" name="note" minlength="3" maxlength="200" placeholder="mis. biaya admin bulanan" required></div>
      <button class="btn btn-primary btn-block">Simpan mutasi</button>
    </form>
    <p class="muted" style="font-size:13px;margin-top:10px">Penjualan masuk otomatis. Untuk memindahkan saldo ke rekening lain gunakan <a href="{{ route('finance.index') }}">Pindah dana</a>.</p>
  </div>
</div>
@endsection
