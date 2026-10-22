<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="author" content="@2026-Alifiandra"><title>Papan Shift {{ $start->format('d-m-Y') }}</title>
@php $codes = $templates->keyBy('code'); @endphp
<style>
  @page { size: A4 landscape; margin: 10mm; }
  body { font-family: system-ui, sans-serif; color: #111; margin: 0; }
  h1 { font-size: 22pt; margin: 0; } .sub { font-size: 11pt; margin: 2mm 0 5mm; }
  table { width: 100%; border-collapse: collapse; font-size: 12pt; }
  th, td { border: 1.5px solid #333; padding: 2.5mm; text-align: center; }
  th { background: #f1f1f1; } td.n { text-align: left; font-weight: 600; }
  td.c { font-weight: 800; font-size: 14pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .legend { margin-top: 5mm; font-size: 10pt; display: flex; gap: 4mm; flex-wrap: wrap; }
  .legend span { padding: 1mm 3mm; border: 1px solid #333; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .bar { text-align: center; padding: 8px; } @media print { .bar { display: none; } }
</style></head>
<body>
<div class="bar"><button onclick="window.print()">Cetak</button></div>
<h1>Jadwal Shift — {{ $currentBranch->name }}</h1>
<div class="sub">{{ $companyProfile->name ?? '' }} · Minggu {{ $days->first()->translatedFormat('d M') }} – {{ $days->last()->translatedFormat('d M Y') }}</div>
<table>
  <thead><tr><th style="width:24%">Nama</th>@foreach($days as $d)<th>{{ $d->translatedFormat('l') }}<br>{{ $d->format('d/m') }}</th>@endforeach</tr></thead>
  <tbody>@foreach($employees as $e)<tr><td class="n">{{ $e->name }}<br><small style="font-weight:400">{{ ucfirst($e->position) }}</small></td>
    @foreach($days as $d)@php $c = $schedules->get($e->id)?->get($d->toDateString())?->code; @endphp<td class="c" style="background:{{ $c ? ($codes[$c]->color ?? '#fff') : '#fff' }}">{{ $c }}</td>@endforeach</tr>@endforeach</tbody>
</table>
<div class="legend">@foreach($templates as $t)<span style="background:{{ $t->color }}"><b>{{ $t->code }}</b> = {{ $t->name }}{{ $t->start_time ? ' ('.substr($t->start_time,0,5).'–'.substr($t->end_time,0,5).')' : '' }}</span>@endforeach</div>
<p style="font-size:8pt;color:#777;margin-top:4mm">Dicetak {{ now()->format('d/m/Y H:i') }} · {{ config('fnb.signature') }}</p>
</body></html>
