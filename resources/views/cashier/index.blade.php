@extends('layouts.app')
@section('title', 'Shift Kasir')
@section('content')
<div class="page-head"><div><h1>Shift kasir</h1><p>Saldo awal, kas masuk/keluar, setoran, lalu closing.</p></div>
  <a href="{{ route('closings.index') }}" class="btn">History closing</a></div>
@if(!$shift)
  <div class="card card-pad" style="max-width:460px">
    <h2>Buka shift</h2><p class="muted">Hitung uang di laci sebelum mulai melayani.</p>
    <form method="POST" action="{{ route('cashier.open') }}" style="margin-top:12px">@csrf
      <div class="field"><label>Saldo awal kas (Rp)</label><input type="number" name="opening_cash" min="0" value="0" required class="btn-lg" style="font-size:18px"></div>
      <button class="btn btn-primary btn-block btn-lg">Buka shift</button></form>
  </div>
@else
  <div class="grid g4" style="margin-bottom:16px">
    <div class="card stat"><div class="label">Saldo awal</div><div class="value">{{ rupiah($summary['opening_cash']) }}</div><div class="hint">{{ $shift->number }} · sejak {{ $shift->opened_at->format('H:i') }}</div></div>
    <div class="card stat"><div class="label">Penjualan tunai</div><div class="value">{{ rupiah($summary['cash_sales']) }}</div><div class="hint">{{ $summary['order_count'] }} transaksi lunas</div></div>
    <div class="card stat"><div class="label">Non-tunai</div><div class="value">{{ rupiah($summary['non_cash_total']) }}</div><div class="hint">direkonsiliasi terpisah</div></div>
    <div class="card stat"><div class="label">Kas seharusnya</div><div class="value">{{ rupiah($summary['expected_cash']) }}</div><div class="hint">di laci saat ini</div></div>
  </div>
  <div class="grid g-side">
    <div class="card">
      <div class="card-head"><h3>Kas masuk / keluar shift ini</h3></div>
      <table class="table"><thead><tr><th>Waktu</th><th>Jenis</th><th>Keterangan</th><th class="num">Jumlah</th></tr></thead><tbody>
        @forelse($movements as $m)
          <tr><td>{{ $m->created_at->format('H:i') }}</td><td>{{ ['income'=>'Penerimaan lain','expense'=>'Pengeluaran','deposit'=>'Setoran'][$m->type] }}</td><td>{{ $m->category ? $m->category.' · ' : '' }}{{ $m->note }}</td>
            <td class="num {{ $m->type==='income' ? 'diff-pos' : 'diff-neg' }}">{{ $m->type==='income' ? '+' : '−' }}{{ rupiah($m->amount) }}</td></tr>
        @empty <tr><td colspan="4" class="empty">Belum ada.</td></tr>
        @endforelse
      </tbody></table>
    </div>
    <div class="stack">
      <form method="POST" action="{{ route('cashier.movement') }}" class="card card-pad">@csrf
        <h3 style="margin-bottom:10px">Catat kas</h3>
        <div class="field"><select name="type"><option value="expense">Pengeluaran (beli es, gas, dll.)</option><option value="income">Penerimaan lain</option><option value="deposit">Setoran / transfer kas ke pemilik</option></select></div>
        <div class="grid g2" style="gap:0 10px"><div class="field"><input name="category" placeholder="Kategori"></div><div class="field"><input type="number" name="amount" min="1" placeholder="Jumlah" required></div></div>
        <div class="field"><input name="note" placeholder="Keterangan (wajib)" required></div>
        <button class="btn btn-block">Simpan</button>
      </form>
      <a href="{{ route('cashier.close') }}" class="btn btn-primary btn-lg btn-block">Closing kasir →</a>
    </div>
  </div>
@endif
@endsection
