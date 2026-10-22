@extends('layouts.app')
@section('title', 'Tema')
@section('content')
@php $t = $themeSetting; @endphp
<div class="page-head"><div><h1>Tema tampilan</h1><p>Berlaku untuk semua pengguna & halaman pelanggan usaha ini. Pengguna tetap bisa memilih mode terang/gelap sendiri.</p></div></div>
<div class="grid g-side" x-data="themer()">
  <form method="POST" action="{{ route('settings.theme.update') }}" class="card card-pad">
    @csrf
    <div class="field"><label>Mode bawaan</label><div class="seg">@foreach(['light'=>'Terang','dark'=>'Gelap','system'=>'Ikuti perangkat'] as $k=>$l)<button type="button" :class="{on: mode==='{{ $k }}'}" @click="mode='{{ $k }}'">{{ $l }}</button>@endforeach</div><input type="hidden" name="mode" :value="mode"></div>
    <div class="field"><label>Preset</label><div class="row">
      @foreach($presets as $name => $p)<button type="button" class="chip" :class="{active: preset==='{{ $name }}'}" @click="apply('{{ $name }}', @js($p))"><span style="display:inline-block;width:12px;height:12px;border-radius:50%;background:{{ $p['primary'] }};margin-right:6px;vertical-align:-1px"></span>{{ ucfirst($name) }}</button>@endforeach
    </div><input type="hidden" name="preset" :value="preset"></div>
    <div class="grid g3" style="gap:0 16px">
      @foreach(['primary'=>'Warna utama','accent'=>'Aksen','background'=>'Latar','surface'=>'Permukaan kartu','text'=>'Teks','border'=>'Garis'] as $k => $l)
        <div class="field"><label>{{ $l }}</label><div class="row" style="flex-wrap:nowrap"><input type="color" x-model="c.{{ $k }}"><input name="{{ $k }}" x-model="c.{{ $k }}" class="input-sm mono" pattern="#[0-9A-Fa-f]{6}" required></div></div>
      @endforeach
    </div>
    <div class="field"><label>Font</label><select name="font" x-model="font">@foreach(['Plus Jakarta Sans','Inter','Nunito','Poppins','System'] as $f)<option>{{ $f }}</option>@endforeach</select></div>
    <p :class="contrast < 4.5 ? 'alert alert-warn' : 'muted'" x-text="'Kontras teks vs latar: ' + contrast.toFixed(1) + ':1' + (contrast < 4.5 ? ' — terlalu rendah, sulit dibaca (minimal 4.5:1).' : ' — baik.')"></p>
    <p :class="btnContrast < 3 ? 'alert alert-warn' : 'muted'" x-text="'Kontras tombol utama (teks putih): ' + btnContrast.toFixed(1) + ':1'"></p>
    <div class="row"><button class="btn btn-primary">Simpan tema</button></div>
  </form>
  <div class="stack">
    <div class="card" :style="`background:${c.surface};color:${c.text};border-color:${c.border}`">
      <div class="card-head" :style="`border-color:${c.border}`"><b>Pratinjau</b></div>
      <div class="card-pad" :style="`background:${c.background}`">
        <p>Contoh teks isi halaman.</p>
        <div class="row"><span class="btn" :style="`background:${c.primary};border-color:${c.primary};color:#fff`">Tombol utama</span><span class="btn" :style="`background:${c.accent};border-color:${c.accent};color:#fff`">Aksen</span></div>
        <div class="row" style="margin-top:10px"><span class="badge badge-ok">Lunas</span><span class="badge badge-warn">Menunggu</span><span class="badge badge-danger">Batal</span></div>
      </div>
    </div>
    <form method="POST" action="{{ route('settings.theme.reset') }}" onsubmit="return confirm('Kembalikan tema bawaan?')">@csrf<button class="btn btn-sm">Reset ke bawaan</button></form>
  </div>
</div>
@endsection
@push('scripts')
<script>
function themer() {
  const lum = (hex) => { const v = [1,3,5].map(i => parseInt(hex.substr(i,2),16)/255).map(x => x <= .03928 ? x/12.92 : Math.pow((x+.055)/1.055, 2.4)); return .2126*v[0] + .7152*v[1] + .0722*v[2]; };
  const ratio = (a, b) => { try { const l1 = lum(a), l2 = lum(b); return (Math.max(l1,l2)+.05)/(Math.min(l1,l2)+.05); } catch (e) { return 0; } };
  return {
    mode: @js($t->mode), preset: @js($t->preset), font: @js($t->font),
    c: { primary: @js($t->primary), accent: @js($t->accent), background: @js($t->background), surface: @js($t->surface), text: @js($t->text), border: @js($t->border) },
    apply(name, p) { this.preset = name; Object.assign(this.c, p); },
    get contrast() { return ratio(this.c.text, this.c.background); },
    get btnContrast() { return ratio('#FFFFFF', this.c.primary); },
  };
}
</script>
@endpush
