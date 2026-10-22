@extends('layouts.admin')
@section('title', 'Pengaturan platform')
@section('content')
<div class="page-head"><div><h1>Pengaturan platform</h1><p>Berlaku untuk semua penyewa.</p></div></div>
<div class="grid g-side">
  <form method="POST" action="{{ route('admin.settings.update') }}" class="card card-pad">
    @csrf
    <h3 style="margin-bottom:10px">Pendaftaran & masa aktif</h3>
    <div class="field"><label class="check"><input type="checkbox" name="registration_open" value="1" @checked($values['registration_open'] === '1')> Buka pendaftaran mandiri di <code>/daftar</code></label></div>
    <div class="grid g2" style="gap:0 16px">
      <div class="field"><label>Lama trial (hari)</label><input type="number" name="trial_days" value="{{ $values['trial_days'] }}" min="0" required></div>
      <div class="field"><label>Masa tenggang setelah jatuh tempo (hari)</label><input type="number" name="grace_days" value="{{ $values['grace_days'] }}" min="0" required></div>
    </div>
    <h3 style="margin:10px 0">Rekening pembayaran langganan</h3>
    <div class="grid g2" style="gap:0 16px">
      <div class="field"><label>Bank</label><input name="bank_name" value="{{ $values['bank_name'] }}" required></div>
      <div class="field"><label>No. rekening</label><input name="bank_account" value="{{ $values['bank_account'] }}" required></div>
      <div class="field"><label>Atas nama</label><input name="bank_holder" value="{{ $values['bank_holder'] }}" required></div>
      <div class="field"><label>WhatsApp dukungan</label><input name="support_whatsapp" value="{{ $values['support_whatsapp'] }}" required></div>
    </div>
    <button class="btn btn-primary">Simpan</button>
  </form>
  <div class="card">
    <div class="card-head"><h3>Paket</h3></div>
    <table class="table"><thead><tr><th>Paket</th><th>Harga/bln</th><th>Cabang</th><th>Pengguna</th></tr></thead><tbody>
      @foreach($plans as $p)<tr><td><b>{{ $p['label'] }}</b><br><small>{{ $p['tagline'] }}</small></td><td class="num">{{ rupiah($p['price']) }}</td><td class="num">{{ $p['max_branches'] }}</td><td class="num">{{ $p['max_users'] }}</td></tr>@endforeach
    </tbody></table>
    <p class="muted card-pad" style="margin:0">Harga & modul paket diatur di <code>config/fnb.php</code> → <code>plans</code>.</p>
  </div>
</div>
<div class="grid g-side" style="margin-top:16px;align-items:start">
  <form method="POST" action="{{ route('admin.settings.whatsapp') }}" class="card card-pad" x-data="{ p: @js($values['wa_provider']) }">
    @csrf
    <h3 style="margin-bottom:6px">WhatsApp platform</h3>
    <p class="muted" style="margin-top:0">Mengirim tagihan & konfirmasi pembayaran langganan ke nomor pemilik usaha (diisi saat pendaftaran).</p>
    <div class="field"><label class="check"><input type="checkbox" name="wa_enabled" value="1" @checked($values['wa_enabled'] === '1')> <b>Aktifkan</b></label></div>
    <div class="grid g2" style="gap:0 16px">
      <div class="field"><label for="wa_provider">Penyedia</label><select id="wa_provider" name="wa_provider" x-model="p">@foreach(config('fnb.whatsapp_providers') as $k => $g)<option value="{{ $k }}">{{ $g['label'] }}</option>@endforeach</select></div>
      <div class="field"><label for="wa_token">Token</label><input id="wa_token" type="password" name="wa_token" autocomplete="off" placeholder="{{ $waTokenSet ? 'Tersimpan — kosongkan bila tidak diubah' : '' }}"></div>
      <div class="field" x-show="p === 'wablas'"><label for="wa_base_url">Alamat server Wablas</label><input id="wa_base_url" type="url" name="wa_base_url" value="{{ $values['wa_base_url'] }}" placeholder="https://…"></div>
      <div class="field" x-show="p === 'wablas'"><label for="wa_secret_key">Secret key (opsional)</label><input id="wa_secret_key" type="password" name="wa_secret_key" autocomplete="off"></div>
    </div>
    @foreach(config('fnb.wa_platform_events') as $key => $def)
      <div class="field"><label class="check"><input type="checkbox" name="wa_event_{{ $key }}" value="1" @checked($values['wa_event_'.$key] === '1')> {{ $def['label'] }}</label>
        <textarea name="wa_template_{{ $key }}" rows="3">{{ $values['wa_template_'.$key] ?: $def['template'] }}</textarea>
        <small class="muted">Isian: {{ $def['placeholders'] }}</small></div>
    @endforeach
    <button class="btn btn-primary">Simpan</button>
  </form>
  <div class="stack">
    <form method="POST" action="{{ route('admin.settings.whatsapp.test') }}" class="card card-pad">
      @csrf
      <h3 style="margin-bottom:8px">Uji WhatsApp</h3>
      <div class="field"><label for="test_phone">Nomor tujuan (kosongkan untuk cek token saja)</label><input id="test_phone" name="phone" placeholder="08xxxxxxxxxx"></div>
      <button class="btn">Uji</button>
    </form>
    <div class="card table-wrap">
      <div class="card-head"><h3>Pesan platform terakhir</h3></div>
      <table class="table table-compact"><thead><tr><th>Waktu</th><th>Tujuan</th><th>Status</th></tr></thead><tbody>
        @forelse($waMessages as $m)<tr><td>{{ $m->created_at->format('d/m H:i') }}</td><td class="mono">{{ $m->to }}</td><td>{!! status_badge($m->status) !!}</td></tr>
        @empty<tr><td colspan="3" class="empty">Belum ada pesan.</td></tr>@endforelse
      </tbody></table>
    </div>
  </div>
</div>
@endsection
