<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="author" content="@2026-Alifiandra">
<title>Nota {{ $order->number }}</title>
@php $profile = \App\Models\CompanyProfile::first(); $w = $width === '80' ? 80 : 58; @endphp
<style>
  @page { size: {{ $w }}mm auto; margin: 0; }
  * { box-sizing: border-box; }
  body { margin: 0; font-family: 'Courier New', ui-monospace, monospace; font-size: {{ $w === 80 ? 12 : 11 }}px; color: #000; background: #fff; }
  .paper { width: {{ $w }}mm; padding: 3mm; margin: 0 auto; }
  .c { text-align: center; } .r { text-align: right; } .b { font-weight: 700; }
  .line { border-top: 1px dashed #000; margin: 4px 0; }
  table { width: 100%; border-collapse: collapse; } td { vertical-align: top; padding: 1px 0; }
  img { max-width: 40%; max-height: 18mm; display: block; margin: 0 auto 4px; filter: grayscale(1); }
  .actions { text-align: center; padding: 10px; font-family: system-ui, sans-serif; }
  .actions button, .actions a { padding: 8px 14px; margin: 2px; border: 1px solid #ccc; border-radius: 6px; background: #fff; cursor: pointer; font-size: 14px; color: #000; text-decoration: none; }
  @media print { .actions { display: none; } }
</style>
</head>
<body>
<div class="actions"><button onclick="window.print()">Cetak</button>@if(empty($public))<a href="?w=58">58mm</a><a href="?w=80">80mm</a>@endif</div>
<div class="paper">
  @if($profile?->logo_path)<img src="{{ $profile->logoUrl() }}" alt="">@endif
  <div class="c b" style="font-size:1.2em">{{ $profile->name ?? '' }}</div>
  <div class="c">{{ $order->branch->name }}</div>
  @if($order->branch->address)<div class="c">{{ $order->branch->address }}</div>@endif
  @if($profile?->phone)<div class="c">Telp {{ $profile->phone }}</div>@endif
  @if($profile?->receipt_header)<div class="c" style="margin-top:2px">{{ $profile->receipt_header }}</div>@endif
  <div class="line"></div>
  <table>
    <tr><td>No</td><td class="r">{{ $order->number }}</td></tr>
    <tr><td>Waktu</td><td class="r">{{ ($order->paid_at ?? $order->created_at)->format('d/m/Y H:i') }}</td></tr>
    <tr><td>Jenis</td><td class="r">{{ $order->typeLabel() }}{{ $order->table ? ' · '.$order->table->name : '' }}</td></tr>
    @if($order->cashier)<tr><td>Kasir</td><td class="r">{{ $order->cashier->name }}</td></tr>@endif
    @if($order->customer_name)<tr><td>Pelanggan</td><td class="r">{{ $order->customer_name }}</td></tr>@endif
  </table>
  <div class="line"></div>
  <table>
    @foreach($order->items->where('kitchen_status', '!=', 'cancelled') as $i)
      <tr><td colspan="2">{{ $i->name }}</td></tr>
      @if($i->modifiers)<tr><td colspan="2" style="padding-left:6px">+ {{ collect($i->modifiers)->pluck('name')->implode(', ') }}</td></tr>@endif
      <tr><td style="padding-left:6px">{{ $i->qty }} x {{ rupiah($i->price + $i->modifiers_total, false) }}</td><td class="r">{{ rupiah($i->subtotal, false) }}</td></tr>
    @endforeach
  </table>
  <div class="line"></div>
  <table>
    <tr><td>Subtotal</td><td class="r">{{ rupiah($order->subtotal, false) }}</td></tr>
    @if($order->discount > 0)<tr><td>Diskon</td><td class="r">-{{ rupiah($order->discount, false) }}</td></tr>@endif
    @if($order->service > 0)<tr><td>Service</td><td class="r">{{ rupiah($order->service, false) }}</td></tr>@endif
    @if($order->tax > 0)<tr><td>Pajak</td><td class="r">{{ rupiah($order->tax, false) }}</td></tr>@endif
    <tr class="b"><td>TOTAL</td><td class="r">{{ rupiah($order->total, false) }}</td></tr>
    @foreach($order->payments->where('status', 'success') as $p)
      <tr><td>{{ \App\Models\Payment::METHODS[$p->method] ?? $p->method }}</td><td class="r">{{ rupiah($p->received, false) }}</td></tr>
      @if($p->change > 0)<tr><td>Kembali</td><td class="r">{{ rupiah($p->change, false) }}</td></tr>@endif
    @endforeach
    <tr><td>Status</td><td class="r">{{ $order->payment_status === 'paid' ? 'LUNAS' : strtoupper($order->payment_status) }}</td></tr>
  </table>
  <div class="line"></div>
  @if($reprint)<div class="c b">** CETAK ULANG **</div>@endif
  @if($profile?->receipt_footer)<div class="c">{{ $profile->receipt_footer }}</div>@endif
  <div class="c" style="margin-top:4px">Nota digital: {{ route('track.receipt', $order->tracking_token) }}</div>
</div>
@if(request('autoprint'))<script>window.onload = () => window.print();</script>@endif
</body>
</html>
