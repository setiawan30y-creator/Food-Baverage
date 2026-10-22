<div x-data="{ lat: @js($b->latitude), lng: @js($b->longitude), msg: '' }">
<div class="grid g3" style="gap:0 16px">
  <div class="field"><label>Nama cabang</label><input name="name" value="{{ $b->name }}" required></div>
  <div class="field"><label>Kode</label><input name="code" value="{{ $b->code }}" required maxlength="10"></div>
  <div class="field"><label>Telepon</label><input name="phone" value="{{ $b->phone }}"></div>
</div>
<div class="field"><label>Alamat</label><input name="address" value="{{ $b->address }}"></div>
<div class="grid g3" style="gap:0 16px">
  <div class="field"><label>Latitude</label><input name="latitude" x-model="lat" inputmode="decimal"></div>
  <div class="field"><label>Longitude</label><input name="longitude" x-model="lng" inputmode="decimal"></div>
  <div class="field"><label>Radius absensi (meter)</label><input type="number" name="geofence_radius" value="{{ $b->geofence_radius }}" min="10" required></div>
</div>
<p><button type="button" class="btn btn-sm" @click="navigator.geolocation ? navigator.geolocation.getCurrentPosition(p => { lat = p.coords.latitude.toFixed(7); lng = p.coords.longitude.toFixed(7); msg = 'Lokasi diisi dari perangkat ini (akurasi ±' + Math.round(p.coords.accuracy) + ' m).'; }, () => msg = 'Gagal mengambil lokasi.') : null">Pakai lokasi perangkat ini</button> <small class="muted" x-text="msg"></small></p>
<div class="grid g4" style="gap:0 16px">
  <div class="field"><label>Jam buka</label><input type="time" name="open_time" value="{{ substr($b->open_time, 0, 5) }}" required></div>
  <div class="field"><label>Jam tutup</label><input type="time" name="close_time" value="{{ substr($b->close_time, 0, 5) }}" required></div>
  <div class="field"><label>Pajak (%)</label><input type="number" step="0.01" name="tax_percent" value="{{ (float) $b->tax_percent }}" min="0" max="100" required></div>
  <div class="field"><label>Service charge (%)</label><input type="number" step="0.01" name="service_percent" value="{{ (float) $b->service_percent }}" min="0" max="100" required></div>
  <div class="field"><label>Durasi slot pickup (menit)</label><input type="number" name="pickup_slot_minutes" value="{{ $b->pickup_slot_minutes }}" min="5" required></div>
  <div class="field"><label>Kapasitas pesanan / slot</label><input type="number" name="pickup_capacity_per_slot" value="{{ $b->pickup_capacity_per_slot }}" min="1" required></div>
</div>
<div class="row" style="margin-bottom:14px">
  <label class="check"><input type="checkbox" name="order_ahead_enabled" value="1" @checked($b->order_ahead_enabled)> Order Ahead aktif</label>
  <label class="check"><input type="checkbox" name="weather_widget" value="1" @checked($b->weather_widget)> Widget cuaca</label>
  <label class="check"><input type="checkbox" name="is_active" value="1" @checked($b->is_active)> Cabang aktif</label>
</div>
</div>
