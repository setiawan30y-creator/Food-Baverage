@extends('layouts.app')
@section('title', 'Absen Saya')
@section('content')
<div class="page-head"><div><h1>Absen saya</h1><p>{{ now()->translatedFormat('l, d F Y') }}</p></div></div>
@if(!$employee)
  <div class="alert alert-warn">Akun Anda belum terhubung ke data karyawan. Minta admin menautkan akun di menu Karyawan.</div>
@else
@php $last = $today->last(); $nextType = (!$last || $last->type === 'out') ? 'in' : 'out'; @endphp
<div class="grid g2">
  <div class="card card-pad" x-data="punch(@js($nextType))">
    <h2>{{ $employee->name }}</h2>
    <p class="muted">Jadwal hari ini: <b>{{ $schedule?->code ?? '—' }}</b>{{ $template?->start_time ? ' ('.substr($template->start_time,0,5).'–'.substr($template->end_time,0,5).')' : '' }}</p>
    <div class="alert alert-info" style="margin:12px 0">Lokasi perangkat hanya diambil saat Anda menekan tombol di bawah, untuk mencocokkan dengan radius cabang. Tidak ada pelacakan di luar proses absen.</div>
    <form method="POST" action="{{ route('attendance.punch') }}" x-ref="f">
      @csrf
      <input type="hidden" name="type" :value="type"><input type="hidden" name="latitude" :value="lat"><input type="hidden" name="longitude" :value="lng"><input type="hidden" name="accuracy" :value="acc">
      <div class="field"><input name="note" placeholder="Catatan (opsional)"></div>
      <button type="button" class="btn btn-lg btn-block" :class="type==='in' ? 'btn-primary' : 'btn-accent'" @click="go()" :disabled="busy" x-text="busy ? 'Mengambil lokasi…' : (type==='in' ? 'Check-in sekarang' : 'Check-out sekarang')"></button>
    </form>
    <p class="error-text" x-show="err" x-text="err" style="margin-top:8px"></p>
    <p class="muted" style="margin-top:10px;font-size:12px">GPS tidak tersedia? Minta manajer mencatat koreksi manual (beralasan & teraudit).</p>
  </div>
  <div class="card">
    <div class="card-head"><h3>Riwayat terbaru</h3><a href="{{ route('leaves.index') }}" class="btn btn-sm">Ajukan izin/cuti/lembur</a></div>
    <table class="table"><tbody>
      @forelse($history as $h)<tr><td>{{ $h->occurred_at->translatedFormat('D d/m H:i') }}</td><td>{{ $h->type === 'in' ? 'Masuk' : 'Pulang' }}</td>
        <td>@if($h->flag)<span class="badge badge-warn">{{ ['late'=>'Terlambat','early_leave'=>'Pulang awal','outside_geofence'=>'Di luar radius','manual'=>'Manual'][$h->flag] ?? $h->flag }}</span>@else<span class="badge badge-ok">OK</span>@endif</td>
        <td class="num"><small>{{ $h->distance_m !== null ? $h->distance_m.' m' : '' }}</small></td></tr>
      @empty <tr><td class="empty">Belum ada absensi.</td></tr>@endforelse
    </tbody></table>
  </div>
</div>
@endif
@endsection
@push('scripts')
<script>
function punch(type) {
  return {
    type, lat: '', lng: '', acc: '', busy: false, err: '',
    go() {
      if (!navigator.geolocation) { this.err = 'Perangkat tidak mendukung lokasi.'; return; }
      if (!navigator.onLine) { this.err = 'Tidak ada koneksi. Absen harus terkirim ke server.'; return; }
      this.busy = true; this.err = '';
      navigator.geolocation.getCurrentPosition((p) => {
        this.lat = p.coords.latitude; this.lng = p.coords.longitude; this.acc = Math.round(p.coords.accuracy);
        this.$nextTick(() => this.$refs.f.submit());
      }, (e) => { this.busy = false; this.err = e.code === 1 ? 'Izin lokasi ditolak. Aktifkan izin lokasi untuk situs ini.' : 'Gagal mengambil lokasi. Coba lagi di area terbuka.'; },
      { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    },
  };
}
</script>
@endpush
