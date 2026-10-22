@extends('layouts.app')
@section('title', 'Payroll')
@section('content')
<div class="page-head"><div><h1>Payroll</h1><p>Draft → Dihitung → Disetujui (terkunci) → Dibayar.</p></div></div>
<div class="grid g-side">
  <div class="card table-wrap"><table class="table">
    <thead><tr><th>Nomor</th><th>Periode</th><th>Status</th><th class="num">Total bersih</th></tr></thead><tbody>
    @forelse($runs as $r)<tr><td><a href="{{ route('payroll.show', $r) }}"><b>{{ $r->number }}</b></a></td><td>{{ $r->period_start->format('d/m/Y') }} – {{ $r->period_end->format('d/m/Y') }}</td><td>{!! status_badge($r->status) !!}</td><td class="num">{{ rupiah($r->total_net) }}</td></tr>
    @empty <tr><td colspan="4" class="empty">Belum ada payroll.</td></tr>@endforelse
  </tbody></table>{{ $runs->links() }}</div>
  <form method="POST" action="{{ route('payroll.store') }}" class="card card-pad">@csrf
    <h3 style="margin-bottom:10px">Hitung payroll baru</h3>
    <div class="field"><label>Periode mulai</label><input type="date" name="period_start" value="{{ today()->subMonthNoOverflow()->startOfMonth()->toDateString() }}" required></div>
    <div class="field"><label>Periode akhir</label><input type="date" name="period_end" value="{{ today()->subMonthNoOverflow()->endOfMonth()->toDateString() }}" required></div>
    <div class="field"><label>Cabang</label><select name="branch_id"><option value="">Semua cabang</option>@foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select></div>
    <button class="btn btn-primary btn-block">Hitung</button>
    <small class="muted" style="display:block;margin-top:8px">BPJS, PPh 21, THR & pesangon diisi pada kolom potongan/bonus sesuai peraturan yang berlaku — tarif tidak ditanam di sistem.</small>
  </form>
</div>
@endsection
