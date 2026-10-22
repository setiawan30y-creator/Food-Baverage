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
@endsection
