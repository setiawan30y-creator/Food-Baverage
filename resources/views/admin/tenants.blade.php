@extends('layouts.admin')
@section('title', 'Penyewa')
@section('content')
<div class="page-head"><div><h1>Penyewa</h1><p>Semua usaha yang memakai platform.</p></div>
  <a href="{{ route('admin.tenants.create') }}" class="btn btn-primary"><x-icon name="plus" style="width:16px;height:16px"/> Penyewa baru</a></div>
<form class="filters">
  <div class="field"><label>Cari</label><input type="search" name="q" value="{{ request('q') }}" placeholder="Nama / slug / email"></div>
  <div class="field"><label>Paket</label><select name="plan"><option value="">Semua</option>@foreach($plans as $k => $p)<option value="{{ $k }}" @selected(request('plan') === $k)>{{ $p['label'] }}</option>@endforeach</select></div>
  <div class="field"><label>Status</label><select name="status"><option value="">Semua</option>@foreach(\App\Models\Tenant::STATUS_LABELS as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select></div>
  <button class="btn">Terapkan</button>
</form>
<div class="card table-wrap"><table class="table table-compact">
  <thead><tr><th>Usaha</th><th>Pemilik</th><th>Paket</th><th>Status</th><th>Berakhir</th><th>Cabang</th><th>Pengguna</th><th>Daftar</th></tr></thead><tbody>
  @forelse($tenants as $t)
    <tr><td><a href="{{ route('admin.tenants.show', $t) }}"><b>{{ $t->name }}</b></a><br><small>/{{ $t->slug }}</small></td>
      <td>{{ $t->owner_name ?? '—' }}<br><small>{{ $t->email }}</small></td>
      <td>{{ $t->planLabel() }}</td><td>@include('partials.tenant-status', ['t' => $t])</td>
      <td>{{ $t->accessEndsAt()?->format('d/m/Y') ?? '—' }}@if($t->daysLeft() !== null)<br><small>{{ $t->daysLeft() >= 0 ? $t->daysLeft().' hari lagi' : abs($t->daysLeft()).' hari lalu' }}</small>@endif</td>
      <td class="num">{{ $t->branches_count }}/{{ $t->max_branches }}</td><td class="num">{{ $t->users_count }}/{{ $t->max_users }}</td><td>{{ $t->created_at->format('d/m/Y') }}</td></tr>
  @empty <tr><td colspan="8" class="empty">Belum ada penyewa.</td></tr>
  @endforelse
</tbody></table></div>
@endsection
