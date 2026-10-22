@extends('layouts.admin')
@section('title', $tenant->name)
@section('content')
<div class="page-head">
  <div><a href="{{ route('admin.tenants') }}">← Penyewa</a><h1>{{ $tenant->name }}</h1>
    <p>/{{ $tenant->slug }} · {{ $tenant->planLabel() }} · @include('partials.tenant-status', ['t' => $tenant]) @if($tenant->suspended_reason)<small>({{ $tenant->suspended_reason }})</small>@endif</p></div>
  <div class="row">
    <form method="POST" action="{{ route('admin.tenants.impersonate', $tenant) }}" onsubmit="return confirm('Masuk sebagai pemilik usaha ini? Tindakan dicatat di audit.')">@csrf<button class="btn">Masuk sebagai pemilik</button></form>
    @if($tenant->effectiveStatus() === 'suspended')
      <form method="POST" action="{{ route('admin.tenants.activate', $tenant) }}">@csrf<button class="btn btn-accent">Aktifkan kembali</button></form>
    @endif
  </div>
</div>
<div class="grid g4" style="margin-bottom:16px">
  <div class="card stat"><div class="label">Berakhir</div><div class="value" style="font-size:20px">{{ $tenant->accessEndsAt()?->translatedFormat('d M Y') ?? '—' }}</div><div class="hint">{{ $tenant->daysLeft() !== null ? ($tenant->daysLeft() >= 0 ? $tenant->daysLeft().' hari lagi' : abs($tenant->daysLeft()).' hari lalu') : '' }}</div></div>
  <div class="card stat"><div class="label">Cabang</div><div class="value">{{ $branches->count() }}/{{ $tenant->max_branches }}</div></div>
  <div class="card stat"><div class="label">Pengguna</div><div class="value">{{ $users->count() }}/{{ $tenant->max_users }}</div></div>
  <div class="card stat"><div class="label">Pesanan 30 hari</div><div class="value">{{ $stats['orders_30d'] }}</div><div class="hint">login terakhir {{ $stats['last_login'] ? \Illuminate\Support\Carbon::parse($stats['last_login'])->format('d/m H:i') : '—' }}</div></div>
</div>
<div class="grid g-side">
  <div class="stack">
    <form method="POST" action="{{ route('admin.tenants.update', $tenant) }}" class="card card-pad">
      @csrf @method('PUT')
      <h3 style="margin-bottom:10px">Data & paket</h3>
      <div class="grid g2" style="gap:0 16px">
        <div class="field"><label>Nama usaha</label><input name="name" value="{{ $tenant->name }}" required></div>
        <div class="field"><label>Pemilik</label><input name="owner_name" value="{{ $tenant->owner_name }}"></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="{{ $tenant->email }}"></div>
        <div class="field"><label>Telepon</label><input name="phone" value="{{ $tenant->phone }}"></div>
        <div class="field"><label>Paket</label><select name="plan">@foreach($plans as $k => $p)<option value="{{ $k }}" @selected($tenant->plan === $k)>{{ $p['label'] }}</option>@endforeach</select></div>
        <div class="grid g2" style="gap:0 10px">
          <div class="field"><label>Maks. cabang</label><input type="number" name="max_branches" value="{{ $tenant->max_branches }}" min="1" required></div>
          <div class="field"><label>Maks. pengguna</label><input type="number" name="max_users" value="{{ $tenant->max_users }}" min="1" required></div>
        </div>
        <div class="field"><label>Trial berakhir</label><input type="date" name="trial_ends_at" value="{{ $tenant->trial_ends_at?->toDateString() }}"></div>
        <div class="field"><label>Langganan berakhir</label><input type="date" name="subscription_ends_at" value="{{ $tenant->subscription_ends_at?->toDateString() }}"><small>Kosong = masih trial.</small></div>
      </div>
      <div class="field"><label>Catatan internal</label><textarea name="notes" rows="2">{{ $tenant->notes }}</textarea></div>
      <button class="btn btn-primary">Simpan</button>
    </form>
    <div class="card">
      <div class="card-head"><h3>Tagihan</h3></div>
      <div class="table-wrap"><table class="table table-compact">
        <thead><tr><th>Nomor</th><th>Paket</th><th>Jumlah</th><th>Status</th><th>Periode</th><th>Aksi</th></tr></thead><tbody>
        @forelse($invoices as $i)
          <tr><td>{{ $i->number }}<br><small>{{ $i->created_at->format('d/m/Y') }}</small></td><td>{{ config('fnb.plans.'.$i->plan.'.label') }} · {{ $i->months }} bln</td>
            <td class="num">{{ rupiah($i->amount) }}</td><td>@include('partials.invoice-status', ['i' => $i])</td>
            <td>{{ $i->period_start ? $i->period_start->format('d/m/y').' – '.$i->period_end->format('d/m/y') : '—' }}</td>
            <td>@if($i->proof_path)<a class="btn btn-sm" href="{{ route('admin.invoices.proof', $i->id) }}" target="_blank">Bukti</a>@endif
              @if($i->isOpen())<form method="POST" action="{{ route('admin.invoices.paid', $i->id) }}" style="display:inline" onsubmit="return confirm('Tandai lunas & perpanjang langganan?')">@csrf<button class="btn btn-sm btn-accent">Lunas</button></form>@endif</td></tr>
        @empty <tr><td colspan="6" class="empty">Belum ada tagihan.</td></tr>
        @endforelse
      </tbody></table></div>
      <form method="POST" action="{{ route('admin.tenants.invoices.store', $tenant) }}" class="card-pad row" style="border-top:1px solid var(--border)">
        @csrf
        <select name="plan" style="width:auto">@foreach($plans as $k => $p)<option value="{{ $k }}" @selected($tenant->plan === $k)>{{ $p['label'] }}</option>@endforeach</select>
        <select name="months" style="width:auto">@foreach(config('fnb.billing_months') as $m => $l)<option value="{{ $m }}">{{ $l }}</option>@endforeach</select>
        <input name="note" placeholder="Catatan (opsional)" style="flex:1;min-width:160px">
        <button class="btn btn-sm">Buat tagihan</button>
      </form>
    </div>
  </div>
  <div class="stack">
    <div class="card">
      <div class="card-head"><h3>Pengguna</h3></div>
      <table class="table"><tbody>
        @foreach($users as $u)<tr><td>{{ $u->name }}<br><small>{{ $u->email }}</small></td><td>{{ $u->roleLabel() }}</td><td>{!! $u->is_active ? status_badge('active') : status_badge('inactive') !!}</td></tr>@endforeach
      </tbody></table>
    </div>
    <div class="card">
      <div class="card-head"><h3>Cabang</h3></div>
      <table class="table"><tbody>
        @foreach($branches as $b)<tr><td>{{ $b->name }}<br><small>{{ $b->address }}</small></td><td>{!! $b->is_active ? status_badge('active') : status_badge('inactive') !!}</td></tr>@endforeach
      </tbody></table>
    </div>
    @if($tenant->effectiveStatus() !== 'suspended')
    <details class="card card-pad disclose"><summary>Tangguhkan penyewa</summary>
      <form method="POST" action="{{ route('admin.tenants.suspend', $tenant) }}" style="margin-top:10px">@csrf
        <p class="muted">Penyewa tidak bisa memakai aplikasi; data tetap tersimpan dan bisa diaktifkan kembali.</p>
        <div class="field"><label>Alasan</label><input name="reason" required minlength="3"></div>
        <button class="btn btn-danger">Tangguhkan</button></form>
    </details>
    @endif
    <div class="card">
      <div class="card-head"><h3>Riwayat platform</h3></div>
      @forelse($logs as $l)<div style="padding:9px 18px;border-bottom:1px solid var(--border)"><b>{{ $l->action }}</b><br><small>{{ $l->created_at->format('d/m/Y H:i') }} · {{ $l->user?->name ?? 'Sistem' }}{{ $l->reason ? ' · '.$l->reason : '' }}</small></div>
      @empty <div class="empty">—</div>@endforelse
    </div>
  </div>
</div>
@endsection
