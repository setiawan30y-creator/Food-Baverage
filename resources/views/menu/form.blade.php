@extends('layouts.app')
@section('title', $menu->exists ? $menu->name : 'Menu baru')
@section('content')
@php $days = $menu->available_days ? array_map('intval', explode(',', $menu->available_days)) : [1,2,3,4,5,6,7]; @endphp
<div class="page-head">
  <div><a href="{{ route('menus.index') }}">← Menu</a><h1>{{ $menu->exists ? $menu->name : 'Menu baru' }}</h1></div>
  @if($menu->exists)
    <div class="row">
      @if($menu->recipe)<a class="btn" href="{{ route('recipes.edit', $menu->recipe) }}">Resep & HPP</a>@else<a class="btn" href="{{ route('recipes.create', ['menu_id' => $menu->id]) }}">Buat resep</a>@endif
    </div>
  @endif
</div>
<div class="grid g-side">
  <form method="POST" action="{{ $menu->exists ? route('menus.update', $menu) : route('menus.store') }}" enctype="multipart/form-data" class="card card-pad">
    @csrf @if($menu->exists) @method('PUT') @endif
    <div class="grid g2" style="gap:0 16px">
      <div class="field"><label>Nama menu</label><input name="name" value="{{ old('name', $menu->name) }}" required placeholder="Nama menu"></div>
      <div class="field"><label>Kategori</label><select name="menu_category_id"><option value="">—</option>@foreach($categories as $c)<option value="{{ $c->id }}" @selected(old('menu_category_id', $menu->menu_category_id) == $c->id)>{{ $c->name }}</option>@endforeach</select></div>
      <div class="field"><label>Harga dasar (Rp)</label><input type="number" name="price" value="{{ old('price', (int) $menu->price) }}" min="0" required @if($menu->exists && !auth()->user()->hasAbility('menu.price')) readonly @endif>
        @if($menu->exists && !auth()->user()->hasAbility('menu.price'))<small>Perubahan harga memerlukan manajer.</small>@endif</div>
      <div class="field"><label>SKU (opsional)</label><input name="sku" value="{{ old('sku', $menu->sku) }}"></div>
      <div class="field"><label>Stasiun dapur</label><input name="station_name" value="{{ old('station_name', $menu->station_name) }}" list="stations" required><datalist id="stations">@foreach($stations as $s)<option value="{{ $s }}">@endforeach</datalist><small>Item dikirim ke layar dapur stasiun ini.</small></div>
      <div class="field"><label>Estimasi waktu siap (menit)</label><input type="number" name="prep_minutes" value="{{ old('prep_minutes', $menu->prep_minutes) }}" min="0" required></div>
    </div>
    <div class="field"><label>Deskripsi</label><textarea name="description" rows="2" placeholder="Deskripsi singkat untuk pelanggan">{{ old('description', $menu->description) }}</textarea></div>
    <div class="field"><label>Jadwal tersedia</label>
      <div class="row">@foreach([1=>'Sen',2=>'Sel',3=>'Rab',4=>'Kam',5=>'Jum',6=>'Sab',7=>'Min'] as $d => $l)<label class="check"><input type="checkbox" name="available_days[]" value="{{ $d }}" @checked(in_array($d, old('available_days', $days)))> {{ $l }}</label>@endforeach</div>
      <div class="row" style="margin-top:8px"><span class="muted">Jam</span><input type="time" name="available_from" value="{{ old('available_from', $menu->available_from ? substr($menu->available_from, 0, 5) : '') }}" style="width:auto"><span>–</span><input type="time" name="available_until" value="{{ old('available_until', $menu->available_until ? substr($menu->available_until, 0, 5) : '') }}" style="width:auto"><small>kosongkan = sepanjang jam buka</small></div>
    </div>
    <div class="field" x-data="{ preview: @js($menu->photoUrl()) }"><label>Foto</label>
      <img x-show="preview" :src="preview" style="width:140px;border-radius:10px;margin-bottom:8px;display:block" alt="Pratinjau foto">
      <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" @change="preview = URL.createObjectURL($event.target.files[0])"><small>PNG/JPG/WebP, maks 2 MB.</small></div>
    <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $menu->is_active))> Menu aktif</label></div>
    <button class="btn btn-primary">Simpan</button>
  </form>

  <div class="stack">
    @if($menu->exists)
    <div class="card">
      <div class="card-head"><h3>Pilihan tambahan</h3></div>
      <div class="card-pad">
        @forelse($menu->modifiers->where('is_active', true)->groupBy('group_name') as $group => $mods)
          <p style="margin:6px 0 2px"><b>{{ $group }}</b></p>
          @foreach($mods as $mod)
            <div class="row between"><span>{{ $mod->name }} <small>{{ $mod->price > 0 ? '+'.rupiah($mod->price) : 'gratis' }}</small></span>
              <form method="POST" action="{{ route('menus.modifiers.destroy', $mod) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm" aria-label="Hapus"><x-icon name="trash" style="width:15px;height:15px"/></button></form></div>
          @endforeach
        @empty <p class="muted">Misal: level pedas, topping, ukuran, gula/es.</p>
        @endforelse
        <form method="POST" action="{{ route('menus.modifiers.store', $menu) }}" style="margin-top:12px">
          @csrf
          <input name="group_name" placeholder="Grup (mis. Level pedas)" class="input-sm" required style="margin-bottom:6px">
          <div class="row"><input name="name" placeholder="Pilihan" class="input-sm" required style="flex:1"><input type="number" name="price" value="0" min="0" class="input-sm" style="width:90px"><button class="btn btn-sm btn-primary">Tambah</button></div>
        </form>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><h3>Harga & stok harian cabang ini</h3></div>
      <form method="POST" action="{{ route('menus.branch', $menu) }}" class="card-pad row">
        @csrf
        @php $bm = \App\Models\BranchMenu::where('branch_id', $currentBranch->id)->where('menu_id', $menu->id)->first(); @endphp
        <div style="flex:1;min-width:140px"><label>Harga cabang</label><input type="number" name="price" value="{{ $bm?->price !== null ? (int) $bm->price : '' }}" placeholder="Kosong = harga dasar" min="0"></div>
        <div style="flex:1;min-width:140px"><label>Stok porsi harian</label><input type="number" name="daily_stock" value="{{ $bm?->daily_stock }}" placeholder="Kosong = tanpa batas" min="0"></div>
        <button class="btn btn-sm" style="align-self:flex-end">Simpan</button>
      </form>
    </div>
    @if(!empty($history) && $history->count())
    <div class="card">
      <div class="card-head"><h3>Riwayat harga</h3></div>
      <table class="table"><tbody>
        @foreach($history as $h)<tr><td><small>{{ $h->created_at->format('d/m/y H:i') }}</small></td><td class="num">{{ $h->old_price !== null ? rupiah($h->old_price) : '—' }} → {{ $h->new_price !== null ? rupiah($h->new_price) : 'dasar' }}</td><td><small>{{ $h->branch_id ? 'cabang' : 'dasar' }} · {{ $h->user?->name }}</small></td></tr>@endforeach
      </tbody></table>
    </div>
    @endif
    @endif
  </div>
</div>
@endsection
