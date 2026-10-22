@extends('layouts.app')
@section('title', $order->number)
@section('content')
@php $u = auth()->user(); @endphp
<div class="page-head">
  <div><h1>{{ $order->number }}</h1><p>{{ $order->channelLabel() }} · {{ $order->typeLabel() }} · {{ $order->created_at->format('d/m/Y H:i') }}</p></div>
  <div class="row">
    {!! status_badge($order->status) !!} {!! status_badge($order->payment_status) !!}
    @if($order->payment_status === 'paid')
      <a href="{{ route('orders.receipt', $order) }}?w=58" target="_blank" class="btn"><x-icon name="print" style="width:16px;height:16px"/> Nota 58mm</a>
      <a href="{{ route('orders.receipt', $order) }}?w=80" target="_blank" class="btn">80mm</a>
    @endif
  </div>
</div>
<div class="grid g-side">
  <div class="stack">
    <div class="card table-wrap">
      <table class="table">
        <thead><tr><th>Item</th><th>Dapur</th><th class="num">Qty</th><th class="num">Harga</th><th class="num">Subtotal</th></tr></thead>
        <tbody>
          @foreach($order->items as $i)
            <tr><td><b>{{ $i->name }}</b>@if($i->modifiers)<br><small>{{ collect($i->modifiers)->pluck('name')->implode(', ') }}</small>@endif @if($i->note)<br><small>“{{ $i->note }}”</small>@endif</td>
              <td>{!! status_badge($i->kitchen_status) !!}<br><small>{{ $i->station_name }}</small></td>
              <td class="num">{{ $i->qty }}</td><td class="num">{{ rupiah($i->price + $i->modifiers_total) }}</td><td class="num">{{ rupiah($i->subtotal) }}</td></tr>
          @endforeach
        </tbody>
        <tfoot>
          <tr><td colspan="4">Subtotal</td><td class="num">{{ rupiah($order->subtotal) }}</td></tr>
          @if($order->discount > 0)<tr><td colspan="4">Diskon <small>({{ $order->discount_reason }})</small></td><td class="num">−{{ rupiah($order->discount) }}</td></tr>@endif
          @if($order->service > 0)<tr><td colspan="4">Service</td><td class="num">{{ rupiah($order->service) }}</td></tr>@endif
          @if($order->tax > 0)<tr><td colspan="4">Pajak</td><td class="num">{{ rupiah($order->tax) }}</td></tr>@endif
          <tr><td colspan="4">Total</td><td class="num" style="font-size:16px">{{ rupiah($order->total) }}</td></tr>
          @if($u->hasAbility('reports'))<tr><td colspan="4"><small>HPP (snapshot) · margin</small></td><td class="num"><small>{{ rupiah($order->cost_total) }} · {{ $order->subtotal > 0 ? number_format(($order->subtotal - $order->discount - $order->cost_total) / max(1, $order->subtotal - $order->discount) * 100, 1, ',', '.') : 0 }}%</small></td></tr>@endif
        </tfoot>
      </table>
    </div>
    <div class="card">
      <div class="card-head"><h3>Riwayat status</h3></div>
      <table class="table"><tbody>
        @foreach($order->histories->sortBy('id') as $h)
          <tr><td style="width:120px">{{ $h->created_at?->format('d/m H:i') }}</td><td>{!! $h->from_status ? status_badge($h->from_status).' → ' : '' !!}{!! status_badge($h->to_status) !!}</td><td class="l">{{ $h->note }}</td><td><small>{{ $h->user?->name ?? 'Pelanggan' }}</small></td></tr>
        @endforeach
      </tbody></table>
    </div>
  </div>
  <div class="stack">
    <div class="card card-pad">
      <dl class="kv">
        <dt>Pelanggan</dt><dd>{{ $order->customer_name ?: '—' }}</dd>
        @if($order->customer_phone)<dt>HP</dt><dd>{{ $order->customer_phone }}</dd>@endif
        @if($order->table)<dt>Meja</dt><dd>{{ $order->table->name }}</dd>@endif
        @if($order->pickup_at)<dt>Ambil</dt><dd>{{ $order->pickup_at->format('d/m H:i') }}</dd>@endif
        @if($order->pickup_code)<dt>Kode ambil</dt><dd><span class="mono" style="font-size:16px">{{ $order->pickup_code }}</span></dd>@endif
        <dt>Kasir</dt><dd>{{ $order->cashier?->name ?? '—' }}</dd>
      </dl>
      @if($order->note)<p style="margin-top:10px"><small>Catatan:</small> {{ $order->note }}</p>@endif
      @if($order->cancel_reason)<div class="alert alert-danger" style="margin:10px 0 0">Dibatalkan: {{ $order->cancel_reason }}</div>@endif
    </div>

    @if(!$order->isFinal())
    <div class="card card-pad stack">
      <h3>Tindakan</h3>
      @if($order->payment_status !== 'paid' && $u->hasAbility('pos'))
        <form method="POST" action="{{ route('orders.pay', $order) }}" x-data="{ m: 'cash' }">
          @csrf
          <input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
          <div class="field"><label>Metode</label><select name="method" x-model="m">@foreach(config('fnb.payment_methods') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
          <div class="field" x-show="m==='cash'"><label>Uang diterima</label><input type="number" name="received" value="{{ (int) $order->total }}" min="0"></div>
          <div class="field" x-show="m!=='cash'"><label>No. referensi (sudah diverifikasi)</label><input type="text" name="reference"></div>
          <button class="btn btn-accent btn-block">Terima pembayaran {{ rupiah($order->total) }}</button>
        </form>
      @endif
      <div class="row">
        @if($order->status === 'new')<form method="POST" action="{{ route('orders.status', $order) }}">@csrf<input type="hidden" name="status" value="accepted"><button class="btn btn-primary">Terima & kirim ke dapur</button></form>@endif
        @if(in_array($order->status, ['accepted', 'preparing']))<form method="POST" action="{{ route('orders.status', $order) }}">@csrf<input type="hidden" name="status" value="ready"><button class="btn">Tandai siap</button></form>@endif
        @if($order->status === 'ready' && $order->payment_status === 'paid')<form method="POST" action="{{ route('orders.status', $order) }}">@csrf<input type="hidden" name="status" value="completed"><button class="btn btn-accent">Selesai / diserahkan</button></form>@endif
      </div>
      <details class="disclose">
        <summary class="btn btn-danger btn-sm" style="display:inline-flex">Batalkan pesanan</summary>
        <form method="POST" action="{{ route('orders.cancel', $order) }}" style="margin-top:10px">
          @csrf
          <div class="field"><label>Alasan (wajib, tercatat di audit)</label><input type="text" name="reason" required minlength="3"></div>
          @if($order->payment_status === 'paid')<p class="muted">Pesanan sudah dibayar — pembayaran akan ditandai refund. Hanya manajer.</p>@endif
          <button class="btn btn-danger">Konfirmasi pembatalan</button>
        </form>
      </details>
    </div>
    @endif

    <div class="card">
      <div class="card-head"><h3>Pembayaran</h3></div>
      @forelse($order->payments as $p)
        <div style="padding:10px 18px;border-bottom:1px solid var(--border)">
          <div class="row between"><b>{{ \App\Models\Payment::METHODS[$p->method] ?? $p->method }}</b>{!! status_badge($p->status) !!}</div>
          <small>{{ rupiah($p->amount) }} · diterima {{ rupiah($p->received) }} · kembali {{ rupiah($p->change) }}{{ $p->reference ? ' · ref '.$p->reference : '' }}<br>{{ $p->paid_at?->format('d/m H:i') }} · {{ $p->user?->name }}</small>
        </div>
      @empty <div class="empty">Belum ada pembayaran.</div>
      @endforelse
    </div>
  </div>
</div>
@endsection
