@extends('layouts.app')
@section('title', 'Slip Gaji Saya')
@section('content')
<div class="page-head"><div><h1>Slip gaji saya</h1></div></div>
<div class="card table-wrap"><table class="table"><thead><tr><th>Periode</th><th>Status</th><th class="num">Bersih</th><th>Slip</th></tr></thead><tbody>
  @forelse($items as $it)<tr><td>{{ $it->run->period_start->format('d/m/Y') }} – {{ $it->run->period_end->format('d/m/Y') }}</td><td>{!! status_badge($it->run->status) !!}</td><td class="num">{{ rupiah($it->net) }}</td><td><a class="btn btn-sm" target="_blank" href="{{ route('payroll.payslip', $it) }}">Lihat slip</a></td></tr>
  @empty <tr><td colspan="4" class="empty">Belum ada slip gaji yang disetujui.</td></tr>@endforelse
</tbody></table></div>
@endsection
