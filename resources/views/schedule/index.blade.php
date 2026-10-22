@extends('layouts.app')
@section('title', 'Jadwal Shift')
@section('content')
@php $codes = $templates->keyBy('code'); @endphp
<div class="page-head"><div><h1>Jadwal shift mingguan</h1><p>{{ $currentBranch->name }} · {{ $days->first()->translatedFormat('d M') }} – {{ $days->last()->translatedFormat('d M Y') }}</p></div>
  <div class="row">
    <a class="btn" href="{{ route('schedule.index', ['week' => $start->copy()->subWeek()->toDateString()]) }}">‹ Minggu lalu</a>
    <a class="btn" href="{{ route('schedule.index') }}">Minggu ini</a>
    <a class="btn" href="{{ route('schedule.index', ['week' => $start->copy()->addWeek()->toDateString()]) }}">Minggu depan ›</a>
    <a class="btn btn-primary" target="_blank" href="{{ route('schedule.print', ['week' => $start->toDateString()]) }}"><x-icon name="print" style="width:16px;height:16px"/> Papan shift A4</a>
  </div></div>
<div class="row" style="margin-bottom:12px">@foreach($templates as $t)<span class="badge" style="background:{{ $t->color }};color:#111">{{ $t->code }} = {{ $t->name }}{{ $t->start_time ? ' '.substr($t->start_time,0,5).'–'.substr($t->end_time,0,5) : '' }}</span>@endforeach</div>
<form method="POST" action="{{ route('schedule.save') }}" class="card table-wrap">
  @csrf
  <table class="table shift-grid">
    <thead><tr><th>Karyawan</th>@foreach($days as $d)<th>{{ $d->translatedFormat('D') }}<br><small>{{ $d->format('d/m') }}</small></th>@endforeach</tr></thead>
    <tbody>
    @forelse($employees as $e)
      <tr><td><b>{{ $e->name }}</b><br><small>{{ ucfirst($e->position) }}</small></td>
        @foreach($days as $d)
          @php $cur = $schedules->get($e->id)?->get($d->toDateString())?->code; @endphp
          <td><select name="cells[{{ $e->id }}][{{ $d->toDateString() }}]" class="shift-cell" style="background:{{ $cur ? ($codes[$cur]->color ?? '') : '' }};color:#111" onchange="this.style.background=this.selectedOptions[0].dataset.c||''">
            <option value=""></option>@foreach($templates as $t)<option value="{{ $t->code }}" data-c="{{ $t->color }}" @selected($cur===$t->code)>{{ $t->code }}</option>@endforeach</select></td>
        @endforeach
      </tr>
    @empty <tr><td colspan="8" class="empty">Belum ada karyawan aktif di cabang ini.</td></tr>
    @endforelse
    </tbody>
    <tfoot><tr><td>Staf bekerja</td>@foreach($days as $d)@php $cv = $coverage[$d->toDateString()] ?? []; $work = collect($cv)->filter(fn ($n, $c) => $codes[$c]->is_working ?? false)->sum(); @endphp<td><b>{{ $work }}</b><br><small>{{ collect($cv)->filter(fn ($n, $c) => $codes[$c]->is_working ?? false)->map(fn ($n, $c) => $c.':'.$n)->implode(' ') }}</small></td>@endforeach</tr></tfoot>
  </table>
  <div class="card-pad"><button class="btn btn-primary">Simpan jadwal</button> <small class="muted">Setiap karyawan hanya punya satu kode per hari (mencegah bentrok jadwal).</small></div>
</form>
<details class="card card-pad disclose" style="margin-top:16px;max-width:640px"><summary>Master kode shift</summary>
  <form method="POST" action="{{ route('schedule.templates') }}" class="row" style="margin-top:12px">@csrf
    <input name="code" placeholder="Kode" style="width:70px" required><input name="name" placeholder="Nama" style="width:120px" required>
    <input type="time" name="start_time" style="width:auto"><input type="time" name="end_time" style="width:auto"><input type="color" name="color" value="#E5E7EB">
    <label class="check"><input type="checkbox" name="is_working" value="1" checked> Hari kerja</label><button class="btn btn-sm btn-primary">Simpan</button></form>
  <small class="muted">Kode yang sama akan diperbarui.</small>
</details>
@endsection
