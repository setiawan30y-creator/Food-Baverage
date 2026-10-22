@extends('layouts.app')
@section('title', 'Karyawan')
@section('content')
<div class="page-head"><div><h1>Karyawan</h1><p>Data pegawai terpisah dari akun login — tidak semua pegawai wajib punya akun.</p></div><a href="{{ route('employees.create') }}" class="btn btn-primary"><x-icon name="plus" style="width:16px;height:16px"/> Karyawan baru</a></div>
<form class="filters">
  <div class="field"><input type="search" name="q" value="{{ request('q') }}" placeholder="Cari nama"></div>
  <div class="field"><select name="status"><option value="">Aktif</option><option value="inactive" @selected(request('status')==='inactive')>Nonaktif</option><option value="resigned" @selected(request('status')==='resigned')>Keluar</option></select></div>
  <div class="field"><select name="branch"><option value="">Cabang ini</option><option value="all" @selected(request('branch')==='all')>Semua cabang</option></select></div>
  <button class="btn">Filter</button>
</form>
<div class="card table-wrap"><table class="table">
  <thead><tr><th>ID</th><th>Nama</th><th>Jabatan</th><th>Cabang</th><th>Metode upah</th><th class="num">Tarif</th><th>Akun</th></tr></thead><tbody>
  @forelse($employees as $e)
    <tr><td class="mono">{{ $e->code }}</td><td><a href="{{ route('employees.edit', $e) }}"><b>{{ $e->name }}</b></a><br><small>{{ $e->phone }}</small></td><td>{{ ucfirst($e->position) }}</td><td>{{ $e->branch?->name ?? 'Semua' }}</td>
      <td>{{ \App\Models\Employee::WAGE_METHODS[$e->wage_method] }}</td><td class="num">{{ $e->wage_method === 'commission' ? qty($e->wage_rate).'%' : rupiah($e->wage_rate) }}</td><td>{{ $e->user?->email ?? '—' }}</td></tr>
  @empty <tr><td colspan="7" class="empty">Belum ada karyawan.</td></tr>
  @endforelse
</tbody></table></div>
@endsection
