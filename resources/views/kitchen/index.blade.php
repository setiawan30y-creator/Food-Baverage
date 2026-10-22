@extends('layouts.app')
@section('title', 'Dapur (KDS)')
@section('content')
<div x-data="kds()" x-init="start()">
  <div class="page-head">
    <div><h1>Layar Dapur</h1><p>Ketuk item untuk ubah status: baru → diproses → siap. Diperbarui otomatis tiap 5 detik.</p></div>
    <div class="row">
      <div class="chips" style="padding:0">
        <a href="{{ route('kitchen.index') }}" class="chip {{ !$station ? 'active' : '' }}">Semua stasiun</a>
        @foreach($stations as $s)<a href="{{ route('kitchen.index', ['station' => $s]) }}" class="chip {{ $station === $s ? 'active' : '' }}">{{ $s }}</a>@endforeach
      </div>
      <span class="badge" :class="online ? 'badge-ok' : 'badge-danger'" x-text="online ? 'Terhubung · ' + time : 'Terputus — data mungkin usang'"></span>
    </div>
  </div>
  <div class="kds">
    <template x-for="o in orders" :key="o.id">
      <div class="ticket" :class="{ late: o.late, ready: o.status==='ready' }">
        <div class="ticket-head">
          <div><b x-text="o.table || o.customer || o.number"></b><br><small x-text="o.channel + ' · ' + o.type + (o.pickup_at ? ' · ambil ' + o.pickup_at : '')"></small></div>
          <div style="text-align:right"><b class="mono" x-text="o.elapsed + ' mnt'"></b><br><small x-text="o.late ? 'Terlambat' : 'est. ' + o.prep + ' mnt'"></small></div>
        </div>
        <template x-for="it in o.items" :key="it.id">
          <div class="ticket-item" :class="'st-' + it.status" @click="advance(it)">
            <div><b x-text="it.qty + '×'"></b> <span x-text="it.name"></span>
              <div x-show="it.mods" style="font-size:12px" x-text="it.mods"></div>
              <div x-show="it.note" style="font-size:12px;font-style:italic" x-text="'“' + it.note + '”'"></div></div>
            <span class="badge" :class="{ 'badge-info': it.status==='new', 'badge-warn': it.status==='preparing', 'badge-ok': it.status==='ready' }" x-text="{new:'Baru',preparing:'Proses',ready:'Siap'}[it.status]"></span>
          </div>
        </template>
        <div x-show="o.note" style="padding:8px 12px;font-size:12px" x-text="'Catatan: ' + o.note"></div>
        <div style="padding:10px 12px" class="row between">
          <small class="mono" x-text="o.number"></small>
          <button x-show="o.status!=='ready'" class="btn btn-sm btn-accent" @click="post(o, 'ready')">Siap semua</button>
          <button x-show="o.status==='ready'" class="btn btn-sm" @click="post(o, 'served')">Sudah disajikan</button>
        </div>
      </div>
    </template>
  </div>
  <div class="card empty" x-show="loaded && !orders.length">Tidak ada antrean. 🎉</div>
</div>
@endsection
@push('scripts')
<script>
function kds() {
  const feed = @js(route('kitchen.feed', ['station' => $station]));
  const base = @js(url('/kitchen'));
  return {
    orders: [], loaded: false, online: true, time: '', known: new Set(),
    start() { this.load(); setInterval(() => this.load(), 5000); },
    async load() {
      try {
        const r = await fetch(feed, { headers: { Accept: 'application/json' } });
        if (!r.ok) throw new Error();
        const j = await r.json();
        const fresh = j.orders.filter(o => !this.known.has(o.id));
        if (this.loaded && fresh.length) this.beep();
        j.orders.forEach(o => this.known.add(o.id));
        this.orders = j.orders; this.time = j.server_time; this.online = true; this.loaded = true;
      } catch (e) { this.online = false; }
    },
    beep() { try { const c = new AudioContext(), o = c.createOscillator(); o.connect(c.destination); o.frequency.value = 880; o.start(); setTimeout(() => o.stop(), 180); } catch (e) {} },
    async advance(it) {
      const next = { new: 'preparing', preparing: 'ready' }[it.status];
      if (!next) return;
      try { await api(base + '/items/' + it.id, { status: next }); it.status = next; } catch (e) { alert(e.message); }
    },
    async post(o, action) {
      try { await api(base + '/orders/' + o.id + '/' + action, {}); this.load(); } catch (e) { alert(e.message); }
    },
  };
}
</script>
@endpush
