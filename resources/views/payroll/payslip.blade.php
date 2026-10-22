<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="author" content="@2026-Alifiandra"><title>Slip Gaji {{ $item->employee->name }}</title>
<style>@page{size:A5;margin:10mm}body{font-family:system-ui,sans-serif;font-size:12px;color:#111;max-width:560px;margin:0 auto}table{width:100%;border-collapse:collapse}td{padding:5px 4px;border-bottom:1px solid #e5e5e5}.r{text-align:right}h1{font-size:16px;margin:0}.bar{text-align:center;padding:8px}@media print{.bar{display:none}}.conf{font-size:10px;color:#a00;text-transform:uppercase;letter-spacing:.08em}</style></head>
<body>
<div class="bar"><button onclick="window.print()">Cetak / Simpan PDF</button></div>
<div class="conf">Rahasia — hanya untuk karyawan bersangkutan</div>
<h1>Slip Gaji · {{ $companyProfile->name ?? '' }}</h1>
<p>Periode {{ $item->run->period_start->format('d/m/Y') }} – {{ $item->run->period_end->format('d/m/Y') }} · {{ $item->run->number }}</p>
<table>
  <tr><td>Nama</td><td class="r"><b>{{ $item->employee->name }}</b> ({{ $item->employee->code }})</td></tr>
  <tr><td>Jabatan</td><td class="r">{{ ucfirst($item->employee->position) }}</td></tr>
  <tr><td>Metode upah</td><td class="r">{{ \App\Models\Employee::WAGE_METHODS[$item->wage_method] }} · {{ qty($item->units) }} × {{ $item->wage_method === 'commission' ? qty($item->wage_rate).'%' : rupiah($item->wage_rate) }}</td></tr>
  <tr><td>Kehadiran</td><td class="r">{{ $item->detail['days_present'] ?? 0 }} hari · {{ $item->detail['hours'] ?? 0 }} jam · lembur {{ $item->detail['overtime_hours'] ?? 0 }} jam</td></tr>
  <tr><td><b>Pendapatan</b></td><td></td></tr>
  <tr><td>Upah pokok</td><td class="r">{{ rupiah($item->base_amount) }}</td></tr>
  <tr><td>Tunjangan</td><td class="r">{{ rupiah($item->allowance) }}</td></tr>
  <tr><td>Lembur</td><td class="r">{{ rupiah($item->overtime) }}</td></tr>
  <tr><td>Bonus / THR</td><td class="r">{{ rupiah($item->bonus) }}</td></tr>
  <tr><td><b>Potongan</b></td><td></td></tr>
  <tr><td>BPJS / PPh 21 / lainnya</td><td class="r">-{{ rupiah($item->deduction) }}</td></tr>
  <tr><td>Cicilan kasbon</td><td class="r">-{{ rupiah($item->advance_deduction) }}</td></tr>
  <tr><td><b>Gaji bersih</b></td><td class="r" style="font-size:15px"><b>{{ rupiah($item->net) }}</b></td></tr>
</table>
<p>Status: {{ ['approved' => 'Disetujui', 'paid' => 'Dibayar'][$item->run->status] ?? $item->run->status }}{{ $item->run->paid_at ? ' · '.$item->run->paid_at->format('d/m/Y') : '' }}</p>
<p style="color:#888;font-size:10px">{{ config('fnb.signature') }}</p>
</body></html>
