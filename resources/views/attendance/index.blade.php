@extends('layouts.app')
@section('title', 'Rekap Absensi')
@section('content')
<div class="page-head"><div><h1>Rekap absensi</h1><p>{{ $currentBranch->name }} · {{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, d F Y') }}</p></div>
  <div class="row"><form class="row"><input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"></form>
    <a class="btn" href="{{ route('attendance.export', ['from' => \Illuminate\Support\Carbon::parse($date)->startOfMonth()->toDateString(), 'to' => $date]) }}">Ekspor bulan ini (CSV)</a></div></div>
@php $flags = ['late'=>'Terlambat','early_leave'=>'Pulang awal','outside_geofence'=>'Di luar radius','manual'=>'Manual']; @endphp
<div class="grid g-side">
  <div class="card table-wrap"><table class="table">
    <thead><tr><th>Karyawan</th><th>Jadwal</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr></thead><tbody>
    @foreach($rows as $r)
      <tr><td><b>{{ $r['employee']->name }}</b><br><small>{{ ucfirst($r['employee']->position) }}</small></td><td>{{ $r['schedule'] ?? '—' }}</td>
        <td>{{ $r['in']?->occurred_at->format('H:i') ?? '—' }}@if($r['in']?->flag)<br><span class="badge badge-warn">{{ $flags[$r['in']->flag] ?? $r['in']->flag }}</span>@endif</td>
        <td>{{ $r['out']?->occurred_at->format('H:i') ?? '—' }}@if($r['out']?->flag)<br><span class="badge badge-warn">{{ $flags[$r['out']->flag] ?? $r['out']->flag }}</span>@endif</td>
        <td>@if($r['missing'])<span class="badge badge-danger">Tidak hadir</span>@elseif($r['in'] && !$r['out'])<span class="badge badge-warn">Belum pulang</span>@elseif($r['in'])<span class="badge badge-ok">Hadir</span>@else — @endif</td></tr>
    @endforeach
  </tbody></table></div>
  <form method="POST" action="{{ route('attendance.manual') }}" class="card card-pad">@csrf
    <h3 style="margin-bottom:10px">Koreksi manual</h3>
    <p class="muted">Untuk kasus GPS tidak tersedia / lupa absen. Tercatat di log audit.</p>
    <div class="field"><label>Karyawan</label><select name="employee_id">@foreach($rows as $r)<option value="{{ $r['employee']->id }}">{{ $r['employee']->name }}</option>@endforeach</select></div>
    <div class="field"><label>Jenis</label><select name="type"><option value="in">Masuk</option><option value="out">Pulang</option></select></div>
    <div class="field"><label>Waktu</label><input type="datetime-local" name="occurred_at" value="{{ $date }}T08:00" required></div>
    <div class="field"><label>Alasan (wajib)</label><input name="note" required minlength="5"></div>
    <button class="btn btn-primary btn-block">Simpan koreksi</button>
  </form>
</div>
@endsection
