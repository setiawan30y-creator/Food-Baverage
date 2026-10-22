@extends('layouts.app')
@section('title', $run->number)
@section('content')
<div class="page-head"><div><a href="{{ route('payroll.index') }}">← Payroll</a><h1>{{ $run->number }}</h1><p>{{ $run->period_start->format('d/m/Y') }} – {{ $run->period_end->format('d/m/Y') }} · {!! status_badge($run->status) !!}@if($run->approver) · disetujui {{ $run->approver->name }}@endif</p></div>
  <div class="row">
    @unless($run->isLocked())<form method="POST" action="{{ route('payroll.calculate', $run) }}">@csrf<button class="btn">Hitung ulang</button></form>@endunless
    @if($run->status==='calculated' && auth()->user()->hasAbility('payroll.approve'))<form method="POST" action="{{ route('payroll.approve', $run) }}" onsubmit="return confirm('Setujui & kunci payroll?')">@csrf<button class="btn btn-primary">Setujui</button></form>@endif
    @if($run->status==='approved' && auth()->user()->hasAbility('payroll.approve'))<form method="POST" action="{{ route('payroll.pay', $run) }}" onsubmit="return confirm('Tandai payroll sudah dibayar?')">@csrf<button class="btn btn-accent">Tandai dibayar</button></form>@endif
  </div></div>
@if($run->isLocked())<div class="alert alert-info">Payroll terkunci. Koreksi dilakukan lewat payroll penyesuaian baru, bukan mengubah data ini.</div>@endif
<div class="card table-wrap"><table class="table table-compact">
  <thead><tr><th>Karyawan</th><th>Metode</th><th class="num">Unit</th><th class="num">Upah pokok</th><th class="num">Tunjangan</th><th class="num">Lembur</th><th class="num">Bonus/THR</th><th class="num">Potongan</th><th class="num">Kasbon</th><th class="num">Bersih</th><th>Aksi</th></tr></thead><tbody>
  @foreach($run->items as $it)
    <tr>
      <td><b>{{ $it->employee->name }}</b><br><small>{{ $it->detail['days_present'] ?? 0 }} hari · {{ $it->detail['hours'] ?? 0 }} jam</small></td>
      <td><small>{{ \App\Models\Employee::WAGE_METHODS[$it->wage_method] }}</small></td>
      @if(!$run->isLocked())
      <td class="num">@if($it->wage_method==='piece')<input type="number" step="any" name="units" form="pi-{{ $it->id }}" value="{{ (float) $it->units }}" class="input-sm" style="width:80px" title="Jumlah borongan" aria-label="Jumlah borongan">@else{{ qty($it->units) }}@endif</td>
      <td class="num">{{ rupiah($it->base_amount) }}</td>
      <td class="num">{{ rupiah($it->allowance) }}</td>
      <td class="num">{{ rupiah($it->overtime) }}</td>
      <td class="num"><input type="number" name="bonus" form="pi-{{ $it->id }}" value="{{ (float) $it->bonus }}" class="input-sm" min="0" style="width:92px" aria-label="Bonus / THR {{ $it->employee->name }}"></td>
      <td class="num"><input type="number" name="deduction" form="pi-{{ $it->id }}" value="{{ (float) $it->deduction }}" class="input-sm" min="0" style="width:92px" aria-label="Potongan {{ $it->employee->name }}"></td>
      <td class="num">{{ rupiah($it->advance_deduction) }}</td>
      <td class="num"><b>{{ rupiah($it->net) }}</b></td>
      <td><form id="pi-{{ $it->id }}" method="POST" action="{{ route('payroll.items.update', $it) }}">@csrf @method('PUT')<button class="btn btn-sm">Simpan</button></form></td>
      @else
      <td class="num">{{ qty($it->units) }}</td><td class="num">{{ rupiah($it->base_amount) }}</td><td class="num">{{ rupiah($it->allowance) }}</td><td class="num">{{ rupiah($it->overtime) }}</td><td class="num">{{ rupiah($it->bonus) }}</td><td class="num">{{ rupiah($it->deduction) }}</td><td class="num">{{ rupiah($it->advance_deduction) }}</td><td class="num"><b>{{ rupiah($it->net) }}</b></td>
      <td><a href="{{ route('payroll.payslip', $it) }}" target="_blank" class="btn btn-sm">Slip</a></td>
      @endif
    </tr>
  @endforeach
  </tbody><tfoot><tr><td colspan="9">Total</td><td class="num">{{ rupiah($run->total_net) }}</td><td></td></tr></tfoot>
</table></div>
@endsection
