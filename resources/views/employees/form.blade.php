@extends('layouts.app')
@section('title', $employee->exists ? $employee->name : 'Karyawan baru')
@section('content')
<div class="page-head"><div><a href="{{ route('employees.index') }}">← Karyawan</a><h1>{{ $employee->exists ? $employee->name : 'Karyawan baru' }}</h1></div></div>
<div class="grid g-side">
<form method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}" class="card card-pad" x-data="{ wm: @js(old('wage_method', $employee->wage_method)) }">
  @csrf @if($employee->exists) @method('PUT') @endif
  <div class="grid g2" style="gap:0 16px">
    <div class="field"><label>ID karyawan</label><input name="code" value="{{ old('code', $employee->code) }}" required placeholder="K001"></div>
    <div class="field"><label>Nama lengkap</label><input name="name" value="{{ old('name', $employee->name) }}" required placeholder="Nama lengkap"></div>
    <div class="field"><label>No. HP</label><input name="phone" value="{{ old('phone', $employee->phone) }}"></div>
    <div class="field"><label>Jabatan</label><select name="position">@foreach(\App\Models\Employee::POSITIONS as $p)<option value="{{ $p }}" @selected(old('position', $employee->position)===$p)>{{ ucfirst($p) }}</option>@endforeach</select></div>
    <div class="field"><label>Cabang penugasan</label><select name="branch_id"><option value="">Semua cabang</option>@foreach($branches as $b)<option value="{{ $b->id }}" @selected(old('branch_id', $employee->branch_id)==$b->id)>{{ $b->name }}</option>@endforeach</select></div>
    <div class="field"><label>Status</label><select name="status">@foreach(['active'=>'Aktif','inactive'=>'Nonaktif','resigned'=>'Keluar'] as $k=>$l)<option value="{{ $k }}" @selected(old('status', $employee->status)===$k)>{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label>Tanggal mulai</label><input type="date" name="start_date" value="{{ old('start_date', $employee->start_date?->toDateString()) }}"></div>
    <div class="field"><label>Tanggal akhir kontrak</label><input type="date" name="end_date" value="{{ old('end_date', $employee->end_date?->toDateString()) }}"></div>
    <div class="field"><label>Akun login (opsional)</label><select name="user_id"><option value="">Tidak punya akun</option>@foreach($users as $u)<option value="{{ $u->id }}" @selected(old('user_id', $employee->user_id)==$u->id)>{{ $u->name }} · {{ $u->email }}</option>@endforeach</select><small>Diperlukan untuk absen GPS & melihat slip gaji sendiri.</small></div>
  </div>
  <hr><h3 style="margin-bottom:10px">Penggajian</h3>
  <div class="grid g2" style="gap:0 16px">
    <div class="field"><label>Metode upah</label><select name="wage_method" x-model="wm">@foreach(\App\Models\Employee::WAGE_METHODS as $k=>$l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
    <div class="field"><label x-text="{monthly:'Gaji per bulan',daily:'Upah per hari hadir',shift:'Upah per shift',hourly:'Upah per jam',commission:'Komisi (% dari penjualan)',piece:'Upah per satuan borongan'}[wm]"></label><input type="number" step="any" min="0" name="wage_rate" value="{{ old('wage_rate', (float) $employee->wage_rate) }}" required></div>
    <div class="field"><label>Tunjangan tetap / periode</label><input type="number" min="0" name="allowance" value="{{ old('allowance', (float) $employee->allowance) }}"></div>
    <div class="field"><label>Upah lembur / jam</label><input type="number" min="0" name="overtime_rate" value="{{ old('overtime_rate', (float) $employee->overtime_rate) }}"><small>Hanya lembur yang disetujui yang dibayar.</small></div>
  </div>
  <button class="btn btn-primary">Simpan</button>
</form>
@if($employee->exists && auth()->user()->hasAbility('payroll'))
<div class="stack">
  <div class="card"><div class="card-head"><h3>Kasbon</h3></div>
    @forelse($employee->advances as $a)<div style="padding:10px 18px;border-bottom:1px solid var(--border)" class="row between"><span>{{ $a->date->format('d/m/Y') }} · {{ rupiah($a->amount) }}<br><small>cicilan {{ rupiah($a->installment) }} · sisa {{ rupiah($a->remaining) }}</small></span>{!! status_badge($a->status === 'settled' ? 'completed' : 'active') !!}</div>
    @empty <div class="empty">Tidak ada kasbon.</div>@endforelse
    <form method="POST" action="{{ route('employees.advances', $employee) }}" class="card-pad">@csrf
      <div class="grid g2" style="gap:6px"><input type="date" name="date" value="{{ today()->toDateString() }}" class="input-sm"><input type="number" name="amount" placeholder="Jumlah" min="1" class="input-sm" required>
        <input type="number" name="installment" placeholder="Cicilan / payroll" min="1" class="input-sm" required><input name="note" placeholder="Keterangan" class="input-sm"></div>
      <button class="btn btn-sm btn-primary" style="margin-top:8px">Catat kasbon</button></form>
  </div>
</div>
@endif
</div>
@endsection
