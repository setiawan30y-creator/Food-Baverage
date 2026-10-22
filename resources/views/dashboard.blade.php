@extends('layouts.app')
@section('title', 'Dasbor')
@section('content')
<div class="page-head">
  <div><h1>Dasbor {{ $currentBranch->name }}</h1><p>{{ now()->translatedFormat('l, d F Y') }} · ringkasan hari ini</p></div>
  <div class="row">
    @if(auth()->user()->hasAbility('pos'))<a href="{{ route('pos.index') }}" class="btn btn-primary"><x-icon name="pos" style="width:16px;height:16px"/> Buka Kasir</a>@endif
    @if(auth()->user()->hasAbility('kitchen'))<a href="{{ route('kitchen.index') }}" class="btn">Layar Dapur</a>@endif
  </div>
</div>

@if(!empty($onboarding))
  @php $done = collect($onboarding)->where(1, true)->count(); @endphp
  <div class="card card-pad" style="margin-bottom:16px">
    <div class="row between"><h3>Langkah awal ({{ $done }}/{{ count($onboarding) }})</h3><div class="bar" style="width:180px"><span style="width:{{ $done / count($onboarding) * 100 }}%"></span></div></div>
    <div class="grid g3" style="gap:8px;margin-top:12px">
      @foreach($onboarding as [$label, $ok, $url])
        <a href="{{ $url }}" class="row" style="gap:10px;padding:10px 12px;border:1px solid var(--border);border-radius:10px;color:var(--text);text-decoration:none;{{ $ok ? 'opacity:.6' : '' }}">
          <span class="badge {{ $ok ? 'badge-ok' : 'badge-muted' }}" style="min-width:24px;justify-content:center">{{ $ok ? '✓' : '·' }}</span><span>{{ $label }}</span></a>
      @endforeach
    </div>
  </div>
@endif
@if($stats['new_online'] > 0)
  <div class="alert alert-info">Ada <b>{{ $stats['new_online'] }}</b> pesanan QR / Order Ahead menunggu diterima. <a href="{{ route('orders.index', ['status' => 'new']) }}">Lihat pesanan →</a></div>
@endif
@if($pendingWaste || $pendingClosing)
  <div class="alert alert-warn">Menunggu persetujuan:
    @if($pendingWaste)<a href="{{ route('inventory.waste.index', ['status' => 'pending']) }}">{{ $pendingWaste }} waste</a>@endif
    @if($pendingWaste && $pendingClosing) · @endif
    @if($pendingClosing)<a href="{{ route('closings.index', ['approval' => 'pending']) }}">{{ $pendingClosing }} closing kasir</a>@endif
  </div>
@endif

<div class="grid g4" style="margin-bottom:16px">
  <div class="card stat"><div class="label">Penjualan lunas</div><div class="value">{{ rupiah($stats['sales']) }}</div><div class="hint">{{ $stats['orders'] }} transaksi</div></div>
  <div class="card stat"><div class="label">Rata-rata / transaksi</div><div class="value">{{ rupiah($stats['avg']) }}</div><div class="hint">hari ini</div></div>
  <div class="card stat"><div class="label">Estimasi laba kotor</div><div class="value">{{ rupiah($stats['sales'] - $stats['cost']) }}</div><div class="hint">HPP {{ rupiah($stats['cost']) }}</div></div>
  <div class="card stat"><div class="label">Pesanan aktif</div><div class="value">{{ $stats['active'] }}</div><div class="hint">belum selesai</div></div>
</div>

<div class="grid g-side">
  <div class="stack">
    <div class="card">
      <div class="card-head"><h3>Penjualan 7 hari</h3><a href="{{ route('reports.sales') }}" class="btn btn-sm">Laporan</a></div>
      <div class="card-pad">
        @php $max = max(1, $trend->max('total')); @endphp
        <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:10px;align-items:end;height:170px">
          @foreach($trend as $d)
            <div style="display:flex;flex-direction:column;align-items:center;gap:6px;height:100%;justify-content:flex-end" title="{{ $d['label'] }}: {{ rupiah($d['total']) }}">
              <small class="mono" style="font-size:11px">{{ $d['total'] >= 1000 ? number_format($d['total'] / 1000, 0, ',', '.').'rb' : '0' }}</small>
              <div style="width:100%;max-width:42px;border-radius:6px 6px 0 0;background:var(--primary);height:{{ max(2, $d['total'] / $max * 120) }}px"></div>
              <small style="font-size:11px">{{ $d['label'] }}</small>
            </div>
          @endforeach
        </div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3>Menu terlaris (7 hari)</h3></div>
      @if($top->isEmpty())<div class="empty">Belum ada penjualan.</div>@else
      <table class="table"><thead><tr><th>Menu</th><th class="num">Terjual</th><th class="num">Omzet</th></tr></thead><tbody>
        @foreach($top as $t)<tr><td>{{ $t->name }}</td><td class="num">{{ qty($t->qty) }}</td><td class="num">{{ rupiah($t->total) }}</td></tr>@endforeach
      </tbody></table>@endif
    </div>
  </div>
  <div class="stack">
    @if($currentBranch->weather_widget && $currentBranch->latitude)
    <div class="card card-pad" x-data="weather({{ $currentBranch->latitude }}, {{ $currentBranch->longitude }})" x-init="load()">
      <div class="row between"><h3>Cuaca cabang</h3><small x-text="updated ? 'Diperbarui ' + updated : ''"></small></div>
      <template x-if="data"><div style="margin-top:8px"><div class="value" style="font-size:28px;font-weight:800"><span x-text="data.temp"></span>°C</div><div x-text="data.label"></div></div></template>
      <p class="muted" x-show="error" x-text="error" style="margin-top:8px"></p>
      <small class="muted" style="display:block;margin-top:8px"><x-icon name="pin" style="width:12px;height:12px"/> {{ $currentBranch->address ?: $currentBranch->name }} · <a href="https://maps.google.com/?q={{ $currentBranch->latitude }},{{ $currentBranch->longitude }}" target="_blank" rel="noopener">Peta</a></small>
    </div>
    @endif
    <div class="card">
      <div class="card-head"><h3>Menu hampir habis</h3><a href="{{ route('menus.index') }}" class="btn btn-sm">Menu</a></div>
      @forelse($menuStock as $row)
        <div style="padding:10px 18px;border-bottom:1px solid var(--border)" class="row between">
          <span>{{ $row['menu']->name }}<br><small>{{ $row['limit_by'] }}</small></span>
          <span class="badge {{ $row['stock'] <= 0 ? 'badge-danger' : 'badge-warn' }}">{{ $row['stock'] <= 0 ? 'Habis' : 'Sisa '.$row['stock'] }}</span>
        </div>
      @empty <div class="empty">Semua menu masih tersedia lebih dari 5 porsi.</div>
      @endforelse
    </div>
    <div class="card">
      <div class="card-head"><h3>Stok bahan menipis</h3><a href="{{ route('inventory.stock') }}" class="btn btn-sm">Stok</a></div>
      @forelse($lowStock as $row)
        <div style="padding:10px 18px;border-bottom:1px solid var(--border)" class="row between">
          <span>{{ $row['item']->name }}</span><span class="badge badge-danger">{{ qty($row['balance']) }} {{ $row['item']->base_unit }}</span>
        </div>
      @empty <div class="empty">Semua stok di atas batas minimum.</div>
      @endforelse
    </div>
    <div class="card card-pad">
      <h3 style="margin-bottom:8px">Tautan pelanggan</h3>
      <p class="muted">Order Ahead (pesan dari rumah, ambil di outlet):</p>
      <input type="text" readonly value="{{ route('oa.index', $currentTenant->slug) }}" onclick="this.select()">
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
function weather(lat, lng) {
  const codes = {0:'Cerah',1:'Cerah berawan',2:'Berawan',3:'Mendung',45:'Berkabut',48:'Berkabut',51:'Gerimis',53:'Gerimis',55:'Gerimis',61:'Hujan ringan',63:'Hujan',65:'Hujan lebat',80:'Hujan lokal',81:'Hujan lokal',82:'Hujan deras',95:'Badai petir',96:'Badai petir',99:'Badai petir'};
  return {
    data: null, error: '', updated: '',
    async load() {
      const key = 'wx:' + lat + ',' + lng;
      try {
        const res = await fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lng}&current=temperature_2m,weather_code&timezone=auto`);
        if (!res.ok) throw new Error();
        const j = await res.json();
        this.data = { temp: Math.round(j.current.temperature_2m), label: codes[j.current.weather_code] || '—' };
        this.updated = new Date().toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit'});
        try { localStorage.setItem(key, JSON.stringify({d: this.data, t: this.updated})); } catch (e) {}
      } catch (e) {
        try { const c = JSON.parse(localStorage.getItem(key)); if (c) { this.data = c.d; this.updated = c.t + ' (data lama)'; } } catch (x) {}
        this.error = 'Gagal memperbarui data cuaca.';
      }
    }
  };
}
</script>
@endpush
