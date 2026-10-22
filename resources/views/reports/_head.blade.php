<div class="tabs">
  <a href="{{ route('reports.sales', request()->only('from','to','scope')) }}" class="{{ request()->routeIs('reports.sales') ? 'active' : '' }}">Penjualan & pembayaran</a>
  <a href="{{ route('reports.menu', request()->only('from','to','scope')) }}" class="{{ request()->routeIs('reports.menu') ? 'active' : '' }}">Menu & margin</a>
  <a href="{{ route('reports.stock', request()->only('from','to')) }}" class="{{ request()->routeIs('reports.stock') ? 'active' : '' }}">Pemakaian & kebocoran stok</a>
  <a href="{{ route('reports.waste', request()->only('from','to')) }}" class="{{ request()->routeIs('reports.waste') ? 'active' : '' }}">Waste</a>
</div>
<form class="filters">
  <div class="field"><label>Dari</label><input type="date" name="from" value="{{ $from->toDateString() }}"></div>
  <div class="field"><label>Sampai</label><input type="date" name="to" value="{{ $to->toDateString() }}"></div>
  @if(!empty($scoped) && auth()->user()->branch_id === null)
    <div class="field"><label>Cakupan</label><select name="scope"><option value="">Cabang aktif</option><option value="all" @selected(request('scope')==='all')>Konsolidasi semua cabang</option></select></div>
  @endif
  <button class="btn">Tampilkan</button>
  @if(!empty($exportable))<a class="btn" href="{{ request()->fullUrlWithQuery(['export' => 1]) }}">Ekspor Excel (CSV)</a>@endif
</form>
