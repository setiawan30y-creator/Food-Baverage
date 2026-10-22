<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="author" content="@2026-Alifiandra"><title>{{ $closing->number }}</title>
<style>@page{size:A4;margin:14mm}body{font-family:system-ui,sans-serif;font-size:12px;color:#111}table{width:100%;border-collapse:collapse}td,th{padding:5px 6px;border-bottom:1px solid #ddd;text-align:left}.r{text-align:right}h1{font-size:18px;margin:0}.bar{text-align:center;padding:8px}@media print{.bar{display:none}}</style></head>
<body>
<div class="bar"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
<h1>Laporan Closing Kasir {{ $closing->number }}</h1>
<p>{{ $companyProfile->name ?? '' }} · {{ $closing->branch->name }}<br>Kasir: {{ $closing->user?->name }} · Shift {{ $closing->shift?->number }} ({{ $closing->shift?->opened_at?->format('d/m/Y H:i') }} – {{ $closing->shift?->closed_at?->format('H:i') ?? '…' }})<br>Final: {{ $closing->finalized_at->format('d/m/Y H:i') }} · Status: {{ $closing->status }} / {{ $closing->approval_status }}</p>
<table>
  <tr><td>Saldo awal</td><td class="r">{{ rupiah($closing->opening_cash) }}</td></tr>
  <tr><td>Penjualan tunai</td><td class="r">{{ rupiah($closing->cash_sales) }}</td></tr>
  <tr><td>Penerimaan lain</td><td class="r">{{ rupiah($closing->other_income) }}</td></tr>
  <tr><td>Pengeluaran</td><td class="r">-{{ rupiah($closing->expenses) }}</td></tr>
  <tr><td>Setoran</td><td class="r">-{{ rupiah($closing->deposits) }}</td></tr>
  <tr><th>Kas seharusnya</th><th class="r">{{ rupiah($closing->expected_cash) }}</th></tr>
  <tr><th>Kas fisik</th><th class="r">{{ rupiah($closing->counted_cash) }}</th></tr>
  <tr><th>Selisih</th><th class="r">{{ rupiah($closing->difference) }}</th></tr>
  @foreach($closing->non_cash ?? [] as $m => $v)<tr><td>Non-tunai: {{ config('fnb.payment_methods.'.$m, $m) }}</td><td class="r">{{ rupiah($v) }}</td></tr>@endforeach
</table>
@if($closing->denominations->isNotEmpty())<h3>Rincian pecahan</h3><table>@foreach($closing->denominations as $d)<tr><td>{{ rupiah($d->denomination) }} × {{ $d->qty }}</td><td class="r">{{ rupiah($d->subtotal) }}</td></tr>@endforeach</table>@endif
@if($closing->reason)<p>Alasan selisih: {{ $closing->reason }}</p>@endif
<table style="margin-top:40px"><tr><td style="border:0;text-align:center">Kasir<br><br><br>( {{ $closing->user?->name }} )</td><td style="border:0;text-align:center">Penyetuju<br><br><br>( {{ $closing->approver?->name ?? '..................' }} )</td></tr></table>
<p style="color:#888;font-size:10px;margin-top:24px">{{ config('fnb.product_name') }} · {{ config('fnb.signature') }}</p>
</body></html>
