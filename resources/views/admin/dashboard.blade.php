@extends('layouts.admin')
@section('title', 'Dasbor')
@section('content')
<div class="page-head"><div><h1>Dasbor platform</h1><p>{{ now()->translatedFormat('l, d F Y') }}</p></div>
  <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary"><x-icon name="plus" style="width:16px;height:16px"/> Penyewa baru</a></div>
<div class="grid g4" style="margin-bottom:16px">
  <div class="card stat"><div class="label">Total penyewa</div><div class="value">{{ $tenants->count() }}</div><div class="hint">{{ $byStatus['active'] ?? 0 }} aktif · {{ $byStatus['trial'] ?? 0 }} trial</div></div>
  <div class="card stat"><div class="label">Pendapatan berulang (MRR)</div><div class="value">{{ rupiah($mrr) }}</div><div class="hint">dari penyewa aktif</div></div>
  <div class="card stat"><div class="label">Diterima bulan ini</div><div class="value">{{ rupiah($revenue) }}</div><div class="hint">tagihan lunas</div></div>
  <div class="card stat"><div class="label">Perlu perhatian</div><div class="value">{{ ($byStatus['past_due'] ?? 0) + ($byStatus['expired'] ?? 0) + ($byStatus['suspended'] ?? 0) }}</div><div class="hint">tenggang · berakhir · ditangguhkan</div></div>
</div>
<div class="grid g2">
  <div class="card">
    <div class="card-head"><h3>Bukti bayar menunggu verifikasi</h3><a href="{{ route('admin.invoices', ['status' => 'waiting']) }}" class="btn btn-sm">Semua</a></div>
    <table class="table"><thead><tr><th>Penyewa</th><th>Tagihan</th><th>Paket</th><th>Jumlah</th></tr></thead><tbody>
      @forelse($waiting as $i)
        <tr><td><a href="{{ route('admin.tenants.show', $i->tenant_id) }}">{{ $i->tenantRecord?->name }}</a></td><td>{{ $i->number }}<br><small>{{ $i->proof_uploaded_at?->format('d/m H:i') }}</small></td>
          <td>{{ config('fnb.plans.'.$i->plan.'.label') }} · {{ $i->months }} bln</td><td class="num">{{ rupiah($i->amount) }}</td></tr>
      @empty <tr><td colspan="4" class="empty">Tidak ada.</td></tr>
      @endforelse
    </tbody></table>
  </div>
  <div class="card">
    <div class="card-head"><h3>Berakhir ≤ 7 hari</h3></div>
    <table class="table"><thead><tr><th>Penyewa</th><th>Paket</th><th>Status</th><th>Sisa hari</th></tr></thead><tbody>
      @forelse($endingSoon as $t)
        <tr><td><a href="{{ route('admin.tenants.show', $t) }}">{{ $t->name }}</a></td><td>{{ $t->planLabel() }}</td><td>@include('partials.tenant-status', ['t' => $t])</td><td class="num">{{ $t->daysLeft() }}</td></tr>
      @empty <tr><td colspan="4" class="empty">Tidak ada.</td></tr>
      @endforelse
    </tbody></table>
  </div>
  <div class="card">
    <div class="card-head"><h3>Penyewa terbaru</h3><a href="{{ route('admin.tenants') }}" class="btn btn-sm">Semua</a></div>
    <table class="table"><thead><tr><th>Usaha</th><th>Paket</th><th>Status</th><th>Daftar</th></tr></thead><tbody>
      @foreach($newest as $t)
        <tr><td><a href="{{ route('admin.tenants.show', $t) }}">{{ $t->name }}</a><br><small>{{ $t->owner_name ?? '—' }}</small></td><td>{{ $t->planLabel() }}</td><td>@include('partials.tenant-status', ['t' => $t])</td><td>{{ $t->created_at->format('d/m/Y') }}</td></tr>
      @endforeach
    </tbody></table>
  </div>
</div>
@endsection
