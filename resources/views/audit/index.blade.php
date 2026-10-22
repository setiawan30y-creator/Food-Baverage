@extends('layouts.app')
@section('title', 'Log Audit')
@section('content')
<div class="page-head"><div><h1>Log audit</h1><p>Catatan tindakan sensitif. Hanya-baca.</p></div></div>
<form class="filters">
  <div class="field"><label>Tindakan</label><select name="action"><option value="">Semua</option>@foreach($actions as $a)<option value="{{ $a }}" @selected(request('action')===$a)>{{ $a }}</option>@endforeach</select></div>
  <div class="field"><label>Tanggal</label><input type="date" name="date" value="{{ request('date') }}"></div>
  <button class="btn">Filter</button>
</form>
<div class="card table-wrap"><table class="table">
  <thead><tr><th>Waktu</th><th>Pengguna</th><th>Tindakan</th><th>Objek</th><th class="l">Alasan</th><th class="l">Sebelum → Sesudah</th></tr></thead><tbody>
  @forelse($logs as $l)<tr><td>{{ $l->created_at->format('d/m/y H:i:s') }}</td><td>{{ $l->user?->name ?? 'Sistem' }}<br><small>{{ $l->branch?->name }} · {{ $l->ip_address }}</small></td><td><span class="badge badge-muted">{{ $l->action }}</span></td>
    <td>{{ $l->auditable_type }}{{ $l->auditable_id ? ' #'.$l->auditable_id : '' }}</td><td class="l">{{ $l->reason }}</td>
    <td class="l"><small class="mono" style="word-break:break-all">{{ $l->before ? json_encode($l->before, JSON_UNESCAPED_UNICODE) : '' }}{{ $l->before && $l->after ? ' → ' : '' }}{{ $l->after ? json_encode($l->after, JSON_UNESCAPED_UNICODE) : '' }}</small></td></tr>
  @empty <tr><td colspan="6" class="empty">Belum ada log.</td></tr>@endforelse
</tbody></table>{{ $logs->links() }}</div>
@endsection
