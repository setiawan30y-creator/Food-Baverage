@extends('layouts.admin')
@section('title', 'Tagihan')
@section('content')
<div class="page-head"><div><h1>Tagihan langganan</h1><p>Verifikasi bukti transfer untuk memperpanjang langganan penyewa.</p></div></div>
<div class="tabs">
  @foreach(['' => 'Terbuka & lunas', 'waiting' => 'Menunggu verifikasi', 'unpaid' => 'Belum bayar', 'paid' => 'Lunas', 'void' => 'Dibatalkan'] as $k => $l)
    <a href="{{ route('admin.invoices', $k ? ['status' => $k] : []) }}" class="{{ request('status', '') === $k ? 'active' : '' }}">{{ $l }}</a>
  @endforeach
</div>
<div class="card table-wrap"><table class="table table-compact">
  <thead><tr><th>Nomor</th><th>Penyewa</th><th>Paket</th><th>Jumlah</th><th>Jatuh tempo</th><th>Status</th><th>Bukti</th><th>Aksi</th></tr></thead><tbody>
  @forelse($invoices as $i)
    <tr x-data="{ v: false }">
      <td><b>{{ $i->number }}</b><br><small>{{ $i->created_at->format('d/m/Y') }}</small></td>
      <td><a href="{{ route('admin.tenants.show', $i->tenant_id) }}">{{ $i->tenantRecord?->name }}</a></td>
      <td>{{ config('fnb.plans.'.$i->plan.'.label') }} · {{ $i->months }} bln</td>
      <td class="num">{{ rupiah($i->amount) }}</td>
      <td>{{ $i->due_date->format('d/m/Y') }}</td>
      <td>@include('partials.invoice-status', ['i' => $i])@if($i->verifier)<br><small>{{ $i->verifier->name }}</small>@endif</td>
      <td>@if($i->proof_path)<a href="{{ route('admin.invoices.proof', $i->id) }}" target="_blank" class="btn btn-sm">Lihat</a>@if($i->payment_ref)<br><small>ref {{ $i->payment_ref }}</small>@endif @else — @endif</td>
      <td>@if($i->isOpen())
        <form method="POST" action="{{ route('admin.invoices.paid', $i->id) }}" style="display:inline" onsubmit="return confirm('Tandai lunas {{ $i->number }} & perpanjang langganan?')">@csrf<button class="btn btn-sm btn-accent">Lunas</button></form>
        <form method="POST" action="{{ route('admin.invoices.void', $i->id) }}" style="display:inline" onsubmit="var r = prompt('Alasan pembatalan'); if (!r) return false; this.reason.value = r;">@csrf<input type="hidden" name="reason"><button class="btn btn-sm btn-danger">Batal</button></form>
      @else — @endif</td>
    </tr>
  @empty <tr><td colspan="8" class="empty">Tidak ada tagihan.</td></tr>
  @endforelse
</tbody></table>{{ $invoices->links() }}</div>
@endsection
