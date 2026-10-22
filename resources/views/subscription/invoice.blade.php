@extends('layouts.app')
@section('title', 'Tagihan '.$invoice->number)
@section('content')
<div class="page-head"><div><a href="{{ route('subscription.index') }}">← Langganan</a><h1>Tagihan {{ $invoice->number }}</h1>
  <p>{{ config('fnb.plans.'.$invoice->plan.'.label') }} · {{ config('fnb.billing_months.'.$invoice->months) }} · @include('partials.invoice-status', ['i' => $invoice])</p></div></div>
<div class="grid g-side">
  <div class="card card-pad">
    <dl class="kv">
      <dt>Paket</dt><dd>{{ config('fnb.plans.'.$invoice->plan.'.label') }}</dd>
      <dt>Durasi</dt><dd>{{ config('fnb.billing_months.'.$invoice->months) }}</dd>
      <dt>Jatuh tempo</dt><dd>{{ $invoice->due_date->translatedFormat('d M Y') }}</dd>
      @if($invoice->period_start)<dt>Periode aktif</dt><dd>{{ $invoice->period_start->format('d/m/Y') }} – {{ $invoice->period_end->format('d/m/Y') }}</dd>@endif
      <dt><b>Total</b></dt><dd style="font-size:22px">{{ rupiah($invoice->amount) }}</dd>
    </dl>
    @if($invoice->isOpen())
      <hr>
      <h3>Cara bayar</h3>
      <p>Transfer tepat <b>{{ rupiah($invoice->amount) }}</b> ke:</p>
      <div class="card card-pad" style="background:var(--subtle)">
        <b>{{ $bank['name'] }}</b><div class="mono" style="font-size:20px;font-weight:800">{{ $bank['account'] }}</div><small>a.n. {{ $bank['holder'] }}</small>
      </div>
      <p class="muted" style="margin-top:8px">Cantumkan nomor tagihan <b>{{ $invoice->number }}</b> pada berita transfer. Butuh bantuan? WhatsApp {{ $support }}.</p>
    @endif
  </div>
  <div class="card card-pad">
    <h3 style="margin-bottom:10px">Bukti pembayaran</h3>
    @if($invoice->proof_path)
      <p>Terkirim {{ $invoice->proof_uploaded_at?->format('d/m/Y H:i') }}. <a href="{{ route('subscription.proof', $invoice) }}" target="_blank">Lihat bukti</a></p>
    @endif
    @if($invoice->status === 'paid')
      <div class="alert alert-ok">Pembayaran terverifikasi {{ $invoice->paid_at?->format('d/m/Y') }}. Terima kasih!</div>
    @elseif($invoice->isOpen())
      <form method="POST" action="{{ route('subscription.proof.upload', $invoice) }}" enctype="multipart/form-data">
        @csrf
        <div class="field"><label>Foto / PDF bukti transfer</label><input type="file" name="proof" accept="image/*,application/pdf" required></div>
        <div class="field"><label>No. referensi (opsional)</label><input name="payment_ref" placeholder="mis. 4 digit akhir rekening pengirim"></div>
        <button class="btn btn-primary btn-block">{{ $invoice->proof_path ? 'Kirim ulang bukti' : 'Kirim bukti pembayaran' }}</button>
      </form>
      @if($invoice->status === 'waiting')<p class="muted" style="margin-top:10px">Bukti sedang diverifikasi. Langganan aktif otomatis setelah disetujui.</p>@endif
    @else
      <p class="muted">Tagihan dibatalkan{{ $invoice->note ? ': '.$invoice->note : '' }}.</p>
    @endif
  </div>
</div>
@endsection
