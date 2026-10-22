@extends('layouts.app')
@section('title', 'Closing Kasir')
@section('content')
<div class="page-head"><div><a href="{{ route('cashier.index') }}">← Shift</a><h1>Closing kasir {{ $shift->number }}</h1><p>Closing final tersimpan sebagai arsip dan tidak dapat diubah tanpa buka ulang beraudit.</p></div></div>
<form method="POST" action="{{ route('cashier.close.store') }}" x-data="closing()" class="grid g-side">
  @csrf
  <div class="card card-pad">
    <div class="seg" style="margin-bottom:14px">
      <button type="button" :class="{on: method==='denomination'}" @click="method='denomination'">Hitung per pecahan</button>
      <button type="button" :class="{on: method==='total'}" @click="method='total'">Input total</button>
    </div>
    <input type="hidden" name="count_method" :value="method">
    <div x-show="method==='denomination'">
      <table class="table"><thead><tr><th>Pecahan</th><th class="num">Jumlah lembar/keping</th><th class="num">Subtotal</th></tr></thead><tbody>
        @foreach($denominations as $d)
          <tr><td>{{ rupiah($d) }}</td><td class="num"><input type="number" min="0" name="denominations[{{ $d }}]" x-model.number="den[{{ $d }}]" class="input-sm" style="width:100px"></td><td class="num" x-text="rupiah((den[{{ $d }}]||0) * {{ $d }})"></td></tr>
        @endforeach
      </tbody></table>
    </div>
    <div x-show="method==='total'" class="field"><label>Total uang fisik di laci</label><input type="number" min="0" name="counted_total" x-model.number="manual" class="btn-lg" style="font-size:20px"></div>
  </div>
  <div class="card card-pad">
    <dl class="kv">
      <dt>Saldo awal</dt><dd>{{ rupiah($summary['opening_cash']) }}</dd>
      <dt>+ Penjualan tunai</dt><dd>{{ rupiah($summary['cash_sales']) }}</dd>
      <dt>+ Penerimaan lain</dt><dd>{{ rupiah($summary['other_income']) }}</dd>
      <dt>− Pengeluaran</dt><dd>{{ rupiah($summary['expenses']) }}</dd>
      <dt>− Setoran</dt><dd>{{ rupiah($summary['deposits']) }}</dd>
      <dt><b>Kas seharusnya</b></dt><dd style="font-size:16px">{{ rupiah($summary['expected_cash']) }}</dd>
      <dt><b>Kas fisik</b></dt><dd style="font-size:16px" x-text="rupiah(counted)"></dd>
      <dt><b>Selisih</b></dt><dd style="font-size:18px" :class="diff < 0 ? 'diff-neg' : (diff > 0 ? 'diff-pos' : '')" x-text="(diff > 0 ? '+' : '') + rupiah(diff) + (diff === 0 ? ' (sesuai)' : (diff < 0 ? ' (kurang)' : ' (lebih)'))"></dd>
    </dl>
    <hr>
    <p class="muted" style="margin-bottom:6px">Non-tunai (rekonsiliasi terpisah dari laci):</p>
    <dl class="kv">@forelse($summary['non_cash'] as $m => $v)<dt>{{ config('fnb.payment_methods.'.$m, $m) }}</dt><dd>{{ rupiah($v) }}</dd>@empty<dt>—</dt><dd>Rp 0</dd>@endforelse</dl>
    <div class="field" style="margin-top:14px" x-show="diff !== 0"><label>Alasan selisih (wajib)</label><textarea name="reason" rows="2"></textarea><small>Closing dengan selisih memerlukan persetujuan manajer.</small></div>
    <button class="btn btn-primary btn-block btn-lg" onclick="return confirm('Finalkan closing? Shift akan ditutup.')">Finalkan closing</button>
  </div>
</form>
@endsection
@push('scripts')
<script>
function closing() {
  return {
    method: 'denomination', den: {}, manual: 0, expected: {{ (float) $summary['expected_cash'] }},
    get counted() { return this.method === 'total' ? (Number(this.manual) || 0) : Object.entries(this.den).reduce((s, [d, q]) => s + Number(d) * (Number(q) || 0), 0); },
    get diff() { return Math.round(this.counted - this.expected); },
  };
}
</script>
@endpush
