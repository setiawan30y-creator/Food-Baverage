<div class="field"><label>Nama</label><input name="name" value="{{ $u->name }}" required placeholder="Nama lengkap"></div>
<div class="field"><label>Email</label><input type="email" name="email" value="{{ $u->email }}" required placeholder="nama@usaha.com"></div>
<div class="field"><label>No. HP</label><input name="phone" value="{{ $u->phone }}"></div>
<div class="field"><label>Peran</label><select name="role">@foreach($roles as $k => $r)<option value="{{ $k }}" @selected($u->role === $k)>{{ $r['label'] }}</option>@endforeach</select></div>
<div class="field"><label>Akses cabang</label><select name="branch_id"><option value="">Semua cabang</option>@foreach($branches as $b)<option value="{{ $b->id }}" @selected($u->branch_id == $b->id)>{{ $b->name }}</option>@endforeach</select></div>
<div class="field"><label>Kata sandi {{ $u->exists ? '(kosongkan bila tidak diubah)' : '' }}</label><input type="password" name="password" autocomplete="new-password" {{ $u->exists ? '' : 'required' }} minlength="8"></div>
<div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked($u->is_active ?? true)> Aktif</label></div>
