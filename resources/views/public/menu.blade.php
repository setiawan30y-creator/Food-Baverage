@extends('layouts.public')
@section('title', $mode === 'qr' ? 'Menu '.$table->name : 'Order Ahead')
@section('subtitle', $branch->name.($mode === 'qr' ? ' · '.$table->name : ' · ambil di outlet'))
@section('content')
@php $defaultPickup = $isOpen ? 'asap' : (collect($slots ?? [])->firstWhere('full', false)['value'] ?? ''); @endphp
<div x-data="shop()" x-cloak>
  @if(!$isOpen)
    <div class="alert alert-warn">Outlet sedang tutup ({{ substr($branch->open_time,0,5) }}–{{ substr($branch->close_time,0,5) }}).{{ $mode === 'order_ahead' ? ' Anda tetap bisa memilih slot pengambilan berikutnya.' : '' }}</div>
  @endif

  <div x-show="step==='menu'">
    <input type="search" x-model="q" placeholder="Cari menu…" style="margin-bottom:10px">
    <div class="chips">
      <button class="chip" :class="{active: cat===null}" @click="cat=null">Semua</button>
      <template x-for="c in categories" :key="c.id"><button class="chip" :class="{active: cat===c.id}" @click="cat=c.id" x-text="c.name"></button></template>
    </div>
    <template x-for="m in filtered" :key="m.id">
      <div class="list-item" :style="m.sold_out ? 'opacity:.5' : ''">
        <template x-if="m.photo"><img :src="m.photo" :alt="m.name" loading="lazy"></template>
        <template x-if="!m.photo"><div class="ph" x-text="m.name.charAt(0)"></div></template>
        <div style="flex:1;min-width:0"><b x-text="m.name"></b><p class="muted" style="font-size:13px;margin:2px 0" x-text="m.description || ''"></p>
          <div class="row between"><b style="color:var(--primary)" x-text="rupiah(m.price)"></b>
            <button class="btn btn-sm btn-primary" @click="pick(m)" :disabled="m.sold_out" x-text="m.sold_out ? 'Habis' : (count(m.id) ? 'Tambah (' + count(m.id) + ')' : 'Tambah')"></button></div></div>
      </div>
    </template>
  </div>

  <div x-show="step==='checkout'">
    <button class="btn btn-ghost btn-sm" @click="step='menu'">← Kembali ke menu</button>
    <h2 style="margin:10px 0">Pesanan Anda</h2>
    <div class="card card-pad">
      <template x-for="(l, i) in cart" :key="l.key">
        <div class="cart-line"><div><b x-text="l.name"></b><br><small x-show="l.mods.length" x-text="l.mods.map(m=>m.name).join(', ')"></small>
          <input type="text" class="input-sm" x-model="l.note" placeholder="Catatan (mis. tanpa sayur)" style="margin-top:4px"></div>
          <div style="text-align:right"><div class="qty"><button @click="dec(i)">−</button><span x-text="l.qty"></span><button @click="l.qty++">+</button></div><div style="margin-top:4px;font-weight:700" x-text="rupiah(line(l))"></div></div></div>
      </template>
      <div class="row between" style="margin-top:12px"><span class="muted">Subtotal</span><b x-text="rupiah(subtotal)"></b></div>
      @if($branch->tax_percent > 0 || $branch->service_percent > 0)<small class="muted">Pajak/service dihitung ulang di server sesuai ketentuan outlet.</small>@endif
    </div>
    <div class="card card-pad" style="margin-top:12px">
      @if($mode === 'order_ahead')
        <div class="field"><label>Nama</label><input x-model="name" placeholder="Nama pemesan" required></div>
        <div class="field"><label>No. WhatsApp</label><input type="tel" x-model="phone" placeholder="08xxxxxxxxxx" required></div>
        <div class="field"><label>Waktu ambil</label>
          <select x-model="pickup">
            @if($isOpen)<option value="asap">Secepatnya (± 20 menit)</option>@endif
            @foreach($slots as $s)<option value="{{ $s['value'] }}" @disabled($s['full'])>{{ $s['label'] }}{{ $s['full'] ? ' — penuh' : '' }}</option>@endforeach
          </select>
          <small class="muted">Waktu adalah estimasi; status "siap diambil" dikonfirmasi staf.</small></div>
        <label class="check" style="margin-bottom:10px"><input type="checkbox" x-model="consent"> Saya bersedia menerima info promo (opsional)</label>
      @else
        <div class="field"><label>Nama (opsional)</label><input x-model="name" placeholder="Agar mudah dipanggil"></div>
      @endif
      <div class="field"><label>Catatan untuk dapur</label><input x-model="note"></div>
      <div class="alert alert-info">Pembayaran dilakukan di kasir{{ $mode === 'order_ahead' ? ' saat mengambil pesanan' : '' }}.</div>
    </div>
  </div>

  {{-- Lembar pilihan tambahan --}}
  <div class="modal-bg" x-show="modMenu" @click.self="modMenu=null">
    <div class="modal card-pad"><template x-if="modMenu"><div>
      <h2 x-text="modMenu.name"></h2>
      <template x-for="(opts, g) in groups(modMenu)" :key="g"><div class="field"><label x-text="g"></label>
        <template x-for="o in opts" :key="o.id"><label class="check" style="margin:4px 14px 4px 0"><input type="checkbox" :value="o.id" x-model.number="sel"> <span x-text="o.name + (o.price ? ' (+' + rupiah(o.price) + ')' : '')"></span></label></template></div></template>
      <button class="btn btn-primary btn-block" @click="add(modMenu, sel); modMenu=null">Tambahkan</button>
    </div></template></div>
  </div>

  <div class="sticky-cart" x-show="cart.length">
    <div class="inner">
      <p class="error-text" x-show="error" x-text="error" style="margin:0 0 6px"></p>
      <button class="btn btn-primary btn-lg btn-block" x-show="step==='menu'" @click="step='checkout'; window.scrollTo(0,0)"><span x-text="items + ' item · ' + rupiah(subtotal)"></span> &nbsp;→ Lanjut</button>
      <button class="btn btn-accent btn-lg btn-block" x-show="step==='checkout'" :disabled="busy" @click="send()" x-text="busy ? 'Mengirim ke outlet…' : 'Kirim pesanan · ' + rupiah(subtotal)"></button>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
function shop() {
  return {
    menus: @js($menus), categories: @js($categories), step: 'menu', q: '', cat: null, cart: [], modMenu: null, sel: [],
    name: '', phone: '', note: '', pickup: @js($defaultPickup), consent: false,
    idem: uuid(), busy: false, error: '',
    get filtered() { const q = this.q.toLowerCase(); return this.menus.filter(m => (this.cat === null || m.category === this.cat) && (!q || m.name.toLowerCase().includes(q))); },
    get subtotal() { return this.cart.reduce((s, l) => s + this.line(l), 0); },
    get items() { return this.cart.reduce((s, l) => s + l.qty, 0); },
    line(l) { return (l.price + l.mods.reduce((s, m) => s + m.price, 0)) * l.qty; },
    count(id) { return this.cart.filter(l => l.menu_id === id).reduce((s, l) => s + l.qty, 0); },
    groups(m) { return m.modifiers.reduce((g, o) => { (g[o.group] = g[o.group] || []).push(o); return g; }, {}); },
    pick(m) { if (m.modifiers.length) { this.modMenu = m; this.sel = []; } else this.add(m, []); },
    add(m, ids) { this.cart.push({ key: m.id + '-' + Date.now(), menu_id: m.id, name: m.name, price: m.price, qty: 1, mods: m.modifiers.filter(o => ids.includes(o.id)), note: '' }); },
    dec(i) { if (this.cart[i].qty > 1) this.cart[i].qty--; else { this.cart.splice(i, 1); if (!this.cart.length) this.step = 'menu'; } },
    async send() {
      this.error = ''; this.busy = true;
      try {
        const r = await api(@js($submitUrl), {
          idempotency_key: this.idem, customer_name: this.name || null, customer_phone: this.phone || null, note: this.note || null,
          pickup: this.pickup, marketing_consent: this.consent,
          items: this.cart.map(l => ({ menu_id: l.menu_id, qty: l.qty, modifier_ids: l.mods.map(m => m.id), note: l.note || null })),
        });
        window.location = r.track_url; // pesanan dianggap diterima hanya setelah server membalas
      } catch (e) { this.error = e.message; this.busy = false; }
    },
  };
}
</script>
@endpush
