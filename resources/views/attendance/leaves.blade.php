@extends('layouts.app')
@section('title', 'Izin, Cuti & Lembur')
@section('content')
<div class="page-head"><div><h1>Izin, sakit, cuti & lembur</h1><p>Lembur hanya dibayar setelah disetujui.</p></div></div>
<div class="grid g-side">
  <div class="card table-wrap"><table class="table">
    <thead><tr><th>Karyawan</th><th>Jenis</th><th>Tanggal</th><th class="l">Alasan</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    @forelse($leaves as $l)
      <tr><td>{{ $l->employee?->name }}</td><td>{{ \App\Models\LeaveRequest::TYPES[$l->type] }}{{ $l->hours ? ' · '.qty($l->hours).' jam' : '' }}</td>
        <td>{{ $l->start_date->format('d/m') }}{{ $l->end_date->ne($l->start_date) ? ' – '.$l->end_date->format('d/m') : '' }}</td><td class="l">{{ $l->reason }}</td><td>{!! status_badge($l->status) !!}</td>
        <td>@if($l->status==='submitted')<div class="row">
          @if($manage)<form method="POST" action="{{ route('leaves.decide', $l) }}" class="row">@csrf<button name="decision" value="approved" class="btn btn-sm btn-accent">Setujui</button><button name="decision" value="rejected" class="btn btn-sm btn-danger">Tolak</button></form>@endif
          <form method="POST" action="{{ route('leaves.cancel', $l) }}">@csrf<button class="btn btn-sm btn-ghost">Batalkan</button></form></div>@endif</td></tr>
    @empty <tr><td colspan="6" class="empty">Belum ada pengajuan.</td></tr>
    @endforelse
  </tbody></table>{{ $leaves->links() }}</div>
  @if($employees->isNotEmpty())
  <form method="POST" action="{{ route('leaves.store') }}" class="card card-pad" x-data="{ t: 'izin' }">@csrf
    <h3 style="margin-bottom:10px">Pengajuan baru</h3>
    <div class="field"><label>Karyawan</label><select name="employee_id">@foreach($employees as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select></div>
    <div class="field"><label>Jenis</label><select name="type" x-model="t">@foreach(\App\Models\LeaveRequest::TYPES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
    <div class="grid g2" style="gap:0 10px"><div class="field"><label>Mulai</label><input type="date" name="start_date" value="{{ today()->toDateString() }}" required></div><div class="field"><label>Sampai</label><input type="date" name="end_date" value="{{ today()->toDateString() }}" required></div></div>
    <div class="field" x-show="t==='lembur'"><label>Jumlah jam lembur</label><input type="number" step="0.5" name="hours" min="0.5" max="24"></div>
    <div class="field"><label>Alasan</label><input name="reason" required></div>
    <button class="btn btn-primary btn-block">Ajukan</button>
  </form>
  @endif
</div>
@endsection
