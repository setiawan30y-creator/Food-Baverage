@extends('layouts.app')
@section('title', 'Kasir')
@section('content')
@if(!$shift)
  <div class="alert alert-warn">Shift kasir Anda belum dibuka. Pesanan bisa dibuat, tetapi pembayaran memerlukan shift aktif. <a href="{{ route('cashier.index') }}">Buka shift →</a></div>
@endif
<div class="pos" x-data="pos()" x-cloak>
  {{-- Daftar menu --}}
  <section class="pos-menu">
    <div class="row" style="margin-bottom:10px">
      <input type="search" x-model="q" placeholder="Cari menu…" style="max-width:260px" aria-label="Cari menu">
      <div class="seg" role="group" aria-label="Jenis pesanan">
        <button type="button" :class="{on: type==='dine_in'}" @click="type='dine_in'">Makan di tempat</button>
        <button type="button" :class="{on: type==='takeaway'}" @click="type='takeaway'; table=''">Bungkus</button>
      </div>
      <select x-show="type==='dine_in'" x-model="table" style="width:auto" aria-label="Meja">
        <option value="">Tanpa meja</option>
        @foreach($tables as $t)<option value="{{ $t->id }}">{{ $t->name }}{{ $t->status !== 'available' ? ' (terisi)' : '' }}</option>@endforeach
      </select>
    </div>
    <div class="chips">
      <button type="button" class="chip" :class="{active: cat===null}" @click="cat=null">Semua</button>
      <template x-for="c in categories" :key="c.id"><button type="button" class="chip" :class="{active: cat===c.id}" @click="cat=c.id" x-text="c.name"></button></template>
    </div>
    <div class="menu-grid">
      <template x-for="m in filtered" :key="m.id">
        <button type="button" class="menu-tile" :class="{soldout: m.sold_out}" @click="pick(m)" :disabled="m.sold_out">
          <template x-if="m.photo"><img :src="m.photo" :alt="m.name" loading="lazy"></template>
          <template x-if="!m.photo"><div class="ph" x-text="m.name.charAt(0)"></div></template>
          <span class="stock-badge" x-show="m.stock !== null" :class="{ low: m.stock > 0 && m.stock <= 5, out: m.stock <= 0 }" x-text="m.stock <= 0 ? 'Stok 0' : 'Sisa ' + m.stock"></span>
          <span class="soldout-label" x-show="m.sold_out">HABIS</span>
          <div class="body"><b x-text="m.name"></b><span x-text="m.sold_out ? 'Habis' : rupiah(m.price)"></span></div>
        </button>
      </template>
      <div class="empty" x-show="filtered.length===0" style="grid-column:1/-1">Menu tidak ditemukan.</div>
    </div>
    @if($openOrders->isNotEmpty())
    <details class="card card-pad disclose" style="margin-top:8px">
      <summary>Pesanan belum dibayar ({{ $openOrders->count() }})</summary>
      <div class="table-wrap" style="margin-top:10px"><table class="table"><tbody>
        @foreach($openOrders as $o)
          <tr><td><a href="{{ route('orders.show', $o) }}">{{ $o->number }}</a><br><small>{{ $o->channelLabel() }}{{ $o->table ? ' · '.$o->table->name : '' }}{{ $o->customer_name ? ' · '.$o->customer_name : '' }}</small></td>
            <td>{!! status_badge($o->status) !!}</td><td class="num">{{ rupiah($o->total) }}</td>
            <td class="num"><button type="button" class="btn btn-sm btn-accent" @click="openPay({ id: {{ $o->id }}, number: @js($o->number), total: {{ (float) $o->total }}, pay_url: @js(route('orders.pay', $o)), receipt_url: @js(route('orders.receipt', $o)) })">Bayar</button></td></tr>
        @endforeach
      </tbody></table></div>
    </details>
    @endif
  </section>

  {{-- Keranjang --}}
  <aside class="card cart">
    <div class="card-head"><h3>Pesanan</h3><button type="button" class="btn btn-ghost btn-sm" @click="reset()" x-show="cart.length">Kosongkan</button></div>
    <div class="cart-items">
      <div class="empty" x-show="!cart.length">Pilih menu untuk mulai.</div>
      <template x-for="(l, i) in cart" :key="l.key">
        <div class="cart-line">
          <div><b x-text="l.name"></b><br>
            <small x-show="l.mods.length" x-text="l.mods.map(m => m.name).join(', ')"></small>
            <input type="text" class="input-sm" x-model="l.note" placeholder="Catatan item" style="margin-top:4px;font-size:12px">
          </div>
          <div style="text-align:right">
            <div class="qty"><button type="button" @click="dec(i)" aria-label="Kurangi">−</button><span x-text="l.qty"></span><button type="button" @click="canAdd(menus.find(m => m.id === l.menu_id)) && l.qty++" aria-label="Tambah">+</button></div>
            <div class="mono" style="margin-top:6px;font-weight:700" x-text="rupiah(lineTotal(l))"></div>
          </div>
        </div>
      </template>
    </div>
    <div class="cart-foot">
      <details class="disclose" style="margin-bottom:10px">
        <summary class="muted">Pelanggan, diskon & catatan</summary>
        <div class="grid g2" style="gap:8px;margin-top:8px">
          <input type="text" x-model="customer_name" placeholder="Nama pelanggan" class="input-sm">
          <input type="tel" x-model="customer_phone" placeholder="No. HP (poin)" class="input-sm">
          <input type="number" x-model.number="discount" min="0" placeholder="Diskon (Rp)" class="input-sm">
          <input type="text" x-model="discount_reason" placeholder="Alasan diskon" class="input-sm">
        </div>
        <input type="text" x-model="note" placeholder="Catatan pesanan" class="input-sm" style="margin-top:8px">
      </details>
      <dl class="kv" style="margin:0 0 8px">
        <dt>Subtotal</dt><dd x-text="rupiah(subtotal)"></dd>
        <template x-if="discount > 0"><dt>Diskon</dt></template><template x-if="discount > 0"><dd x-text="'−' + rupiah(discount)"></dd></template>
        <template x-if="servicePct > 0"><dt x-text="'Service ' + servicePct + '%'"></dt></template><template x-if="servicePct > 0"><dd x-text="rupiah(service)"></dd></template>
        <template x-if="taxPct > 0"><dt x-text="'Pajak ' + taxPct + '%'"></dt></template><template x-if="taxPct > 0"><dd x-text="rupiah(tax)"></dd></template>
      </dl>
      <div class="row between" style="margin-bottom:12px"><span class="muted">Total</span><span class="total-big" x-text="rupiah(total)"></span></div>
      <p class="error-text" x-show="error" x-text="error"></p>
      <div class="grid g2" style="gap:8px">
        <button type="button" class="btn btn-lg" :disabled="!cart.length || busy" @click="submit(false)">Kirim ke dapur</button>
        <button type="button" class="btn btn-lg btn-primary" :disabled="!cart.length || busy" @click="submit(true)" x-text="busy ? 'Memproses…' : 'Bayar'"></button>
      </div>
    </div>
  </aside>

  {{-- Modal pilihan tambahan --}}
  <div class="modal-bg" x-show="modMenu" @keydown.escape.window="modMenu=null" @click.self="modMenu=null">
    <div class="modal card-pad" x-show="modMenu">
      <template x-if="modMenu">
        <div>
          <h2 x-text="modMenu.name"></h2><p class="muted" x-text="rupiah(modMenu.price)"></p>
          <template x-for="(opts, group) in groups(modMenu)" :key="group">
            <div class="field"><label x-text="group"></label>
              <template x-for="o in opts" :key="o.id">
                <label class="check" style="margin:4px 14px 4px 0"><input type="checkbox" :value="o.id" x-model.number="modSel"> <span x-text="o.name + (o.price ? ' (+' + rupiah(o.price) + ')' : '')"></span></label>
              </template>
            </div>
          </template>
          <div class="row" style="justify-content:flex-end"><button type="button" class="btn" @click="modMenu=null">Batal</button><button type="button" class="btn btn-primary" @click="add(modMenu, modSel); modMenu=null">Tambahkan</button></div>
        </div>
      </template>
    </div>
  </div>

  {{-- Modal pembayaran --}}
  <div class="modal-bg" x-show="payOrder" @click.self="!paying && closePay()">
    <div class="modal" x-show="payOrder">
      <template x-if="payOrder">
        <div>
          <div class="card-head"><h2 x-text="'Bayar ' + payOrder.number"></h2><button type="button" class="btn btn-ghost btn-sm" @click="closePay()" :disabled="paying">✕</button></div>
          <div class="card-pad" x-show="!paid">
            <div class="row between"><span class="muted">Total tagihan</span><span class="total-big" x-text="rupiah(payOrder.total)"></span></div>
            <div class="seg" style="margin:14px 0;width:100%;display:flex">
              @foreach(config('fnb.payment_methods') as $k => $label)
                <button type="button" style="flex:1" :class="{on: method==='{{ $k }}'}" @click="setMethod('{{ $k }}')">{{ $label }}</button>
              @endforeach
            </div>
            <div x-show="method==='cash'">
              <label>Uang diterima</label>
              <input type="number" x-model.number="received" min="0" class="btn-lg" style="font-size:20px;font-weight:700">
              <div class="row" style="margin-top:8px">
                <button type="button" class="btn btn-sm" @click="received=payOrder.total">Uang pas</button>
                <template x-for="n in quick" :key="n"><button type="button" class="btn btn-sm" @click="received=n" x-text="rupiah(n)"></button></template>
              </div>
              <div class="row between" style="margin-top:12px"><span class="muted">Kembalian</span><b class="mono" style="font-size:18px" x-text="rupiah(Math.max(0, received - payOrder.total))"></b></div>
            </div>
            <div x-show="method!=='cash'">
              <div class="alert alert-info" style="margin-bottom:10px">Konfirmasi hanya setelah dana benar-benar masuk (cek notifikasi/aplikasi merchant).</div>
              <label>No. referensi / bukti transaksi</label><input type="text" x-model="reference" placeholder="contoh: 4 digit akhir ID transaksi">
            </div>
            <div x-show="accList.length" style="margin-top:10px">
              <label>Masuk ke rekening</label>
              <template x-if="accList.length === 1"><div class="badge badge-muted" style="font-size:13px;padding:6px 10px" x-text="accList[0].label"></div></template>
              <select x-show="accList.length > 1" x-model="account"><option value="">— pilih rekening tujuan —</option><template x-for="a in accList" :key="a.id"><option :value="a.id" x-text="a.label"></option></template></select>
            </div>
            <p class="error-text" x-show="payError" x-text="payError"></p>
            <button type="button" class="btn btn-accent btn-lg btn-block" style="margin-top:14px" @click="pay()" :disabled="paying" x-text="paying ? 'Memverifikasi ke server…' : 'Konfirmasi pembayaran'"></button>
          </div>
          <div class="card-pad" x-show="paid" style="text-align:center">
            <div class="badge badge-ok" style="font-size:14px;padding:6px 14px">Pembayaran tercatat di server</div>
            <p style="margin-top:12px" class="muted">Kembalian</p>
            <div class="total-big" x-text="rupiah(change)"></div>
            <div class="row" style="justify-content:center;margin-top:16px">
              <a class="btn" :href="payOrder.receipt_url + '?w=58'" target="_blank">Cetak nota 58mm</a>
              <a class="btn" :href="payOrder.receipt_url + '?w=80'" target="_blank">80mm</a>
              <button type="button" class="btn btn-primary" @click="closePay(true)">Pesanan baru</button>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</div>
@endsection
@push('scripts')
<script>
function pos() {
  return {
    menus: @js($menus), categories: @js($categories),
    taxPct: {{ (float) $branch->tax_percent }}, servicePct: {{ (float) $branch->service_percent }}, block: @js((bool) $branch->block_out_of_stock),
    q: '', cat: null, type: 'dine_in', table: '', cart: [], customer_name: '', customer_phone: '', discount: 0, discount_reason: '', note: '',
    idem: uuid(), busy: false, error: '', modMenu: null, modSel: [],
    payOrder: null, method: 'cash', received: 0, reference: '', paying: false, paid: false, change: 0, payError: '', payKey: null,
    accounts: @js($accounts), account: '',
    get accList() { return this.accounts[this.method] || []; },
    setMethod(k) { this.method = k; this.account = this.accList.length === 1 ? this.accList[0].id : ''; },
    get filtered() { const q = this.q.toLowerCase(); return this.menus.filter(m => (this.cat === null || m.category === this.cat) && (!q || m.name.toLowerCase().includes(q))); },
    get subtotal() { return this.cart.reduce((s, l) => s + this.lineTotal(l), 0); },
    get base() { return Math.max(0, this.subtotal - (Number(this.discount) || 0)); },
    get service() { return Math.round(this.base * this.servicePct / 100); },
    get tax() { return Math.round((this.base + this.service) * this.taxPct / 100); },
    get total() { return Math.round(this.base + this.service + this.tax); },
    get quick() { const t = this.payOrder ? this.payOrder.total : 0; return [10000, 20000, 50000, 100000].map(n => Math.ceil(t / n) * n).filter((v, i, a) => v > t && a.indexOf(v) === i).slice(0, 3); },
    lineTotal(l) { return (l.price + l.mods.reduce((s, m) => s + m.price, 0)) * l.qty; },
    groups(m) { return m.modifiers.reduce((g, o) => { (g[o.group] = g[o.group] || []).push(o); return g; }, {}); },
    init() { setInterval(() => this.refreshStock(), 30000); },
    inCart(id) { return this.cart.filter(l => l.menu_id === id).reduce((s, l) => s + l.qty, 0); },
    canAdd(m) {
      if (this.block && m.stock !== null && this.inCart(m.id) >= m.stock) { this.error = 'Stok ' + m.name + ' tinggal ' + Math.max(0, m.stock) + ' porsi.'; return false; }
      this.error = ''; return true;
    },
    async refreshStock() {
      try {
        const r = await fetch(@js(route('pos.stock')), { headers: { Accept: 'application/json' } });
        if (!r.ok) return;
        const s = await r.json();
        this.menus.forEach(m => { if (s[m.id]) { m.stock = s[m.id].stock; m.sold_out = s[m.id].sold_out; } });
      } catch (e) {}
    },
    pick(m) { if (m.sold_out || !this.canAdd(m)) return; if (m.modifiers.length) { this.modMenu = m; this.modSel = []; } else { this.add(m, []); } },
    add(m, ids) {
      const mods = m.modifiers.filter(o => ids.includes(o.id));
      const key = m.id + ':' + ids.slice().sort().join(',');
      const ex = this.cart.find(l => l.key === key && !l.note);
      if (ex) ex.qty++; else this.cart.push({ key: key + ':' + Date.now(), menu_id: m.id, name: m.name, price: m.price, qty: 1, mods, note: '' });
    },
    dec(i) { if (this.cart[i].qty > 1) this.cart[i].qty--; else this.cart.splice(i, 1); },
    reset() { this.cart = []; this.customer_name = ''; this.customer_phone = ''; this.discount = 0; this.discount_reason = ''; this.note = ''; this.table = ''; this.error = ''; this.idem = uuid(); },
    async submit(payNow) {
      this.busy = true; this.error = '';
      try {
        const o = await api(@js(route('pos.store')), {
          type: this.type, dining_table_id: this.type === 'dine_in' && this.table ? Number(this.table) : null,
          customer_name: this.customer_name || null, customer_phone: this.customer_phone || null, note: this.note || null,
          discount: Number(this.discount) || 0, discount_reason: this.discount_reason || null, idempotency_key: this.idem,
          items: this.cart.map(l => ({ menu_id: l.menu_id, qty: l.qty, modifier_ids: l.mods.map(m => m.id), note: l.note || null })),
        });
        this.reset();
        this.refreshStock();
        if (payNow) this.openPay(o); else alert('Pesanan ' + o.number + ' terkirim ke dapur.');
      } catch (e) { this.error = e.message; this.refreshStock(); }
      this.busy = false;
    },
    openPay(o) { this.payOrder = o; this.setMethod('cash'); this.received = o.total; this.reference = ''; this.paid = false; this.payError = ''; this.payKey = uuid(); },
    closePay(reload) { this.payOrder = null; if (reload || this.paid) location.reload(); },
    async pay() {
      this.paying = true; this.payError = '';
      try {
        const r = await api(this.payOrder.pay_url, { method: this.method, received: Number(this.received) || 0, reference: this.reference || null, payment_account_id: this.account || null, idempotency_key: this.payKey });
        this.change = r.change; this.paid = true;
      } catch (e) { this.payError = e.message; }
      this.paying = false;
    },
  };
}
</script>
@endpush
