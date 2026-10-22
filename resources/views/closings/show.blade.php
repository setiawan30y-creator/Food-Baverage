@extends('layouts.app')
@section('title', $closing->number)
@section('content')
<div class="page-head"><div><a href="{{ route('closings.index') }}">← History closing</a><h1>{{ $closing->number }}</h1>
  <p>{{ $closing->user?->name }} · {{ $closing->shift?->number }} · final {{ $closing->finalized_at->format('d/m/Y H:i') }} · {!! status_badge($closing->status) !!} {!! status_badge($closing->approval_status) !!}</p></div>
  <a href="{{ route('closings.print', $closing) }}" target="_blank" class="btn"><x-icon name="print" style="width:16px;height:16px"/> Cetak / PDF</a></div>
@if($closing->status === 'reopened')<div class="alert alert-warn">Closing ini telah dibuka ulang. Data di bawah adalah arsip sebelum dibuka ulang.</div>@endif
<div class="grid g-side">
  <div class="stack">
    <div class="card card-pad">
      <dl class="kv">
        <dt>Saldo awal</dt><dd>{{ rupiah($closing->opening_cash) }}</dd>
        <dt>Penjualan tunai</dt><dd>{{ rupiah($closing->cash_sales) }}</dd>
        <dt>Penerimaan lain</dt><dd>{{ rupiah($closing->other_income) }}</dd>
        <dt>Pengeluaran</dt><dd>−{{ rupiah($closing->expenses) }}</dd>
        <dt>Setoran</dt><dd>−{{ rupiah($closing->deposits) }}</dd>
        <dt><b>Kas seharusnya</b></dt><dd>{{ rupiah($closing->expected_cash) }}</dd>
        <dt><b>Kas fisik</b> ({{ $closing->count_method === 'denomination' ? 'per pecahan' : 'total' }})</dt><dd>{{ rupiah($closing->counted_cash) }}</dd>
        <dt><b>Selisih</b></dt><dd class="{{ $closing->difference < 0 ? 'diff-neg' : ($closing->difference > 0 ? 'diff-pos' : '') }}" style="font-size:16px">{{ $closing->difference == 0 ? 'Sesuai' : rupiah($closing->difference) }}</dd>
        <dt>Non-tunai</dt><dd>{{ rupiah($closing->non_cash_total) }}</dd>
        <dt>Jumlah transaksi</dt><dd>{{ $closing->order_count }}</dd>
      </dl>
      @if($closing->reason)<p style="margin-top:12px"><small>Alasan selisih:</small> {{ $closing->reason }}</p>@endif
      @if($closing->approver)<p><small>{{ $closing->approval_status === 'approved' ? 'Disetujui' : 'Ditolak' }} oleh {{ $closing->approver->name }} · {{ $closing->approved_at?->format('d/m/Y H:i') }}</small></p>@endif
    </div>
    @if($closing->denominations->isNotEmpty())
    <div class="card"><div class="card-head"><h3>Hitung per pecahan</h3></div><table class="table"><tbody>
      @foreach($closing->denominations as $d)<tr><td>{{ rupiah($d->denomination) }}</td><td class="num">× {{ $d->qty }}</td><td class="num">{{ rupiah($d->subtotal) }}</td></tr>@endforeach
    </tbody></table></div>
    @endif
    <div class="card"><div class="card-head"><h3>Transaksi terkait ({{ $payments->count() }})</h3></div><div class="table-wrap"><table class="table"><tbody>
      @foreach($payments as $p)<tr><td>{{ $p->paid_at?->format('H:i') }}</td><td><a href="{{ route('orders.show', $p->order) }}">{{ $p->order?->number }}</a></td><td>{{ \App\Models\Payment::METHODS[$p->method] ?? $p->method }}</td><td>{!! status_badge($p->status) !!}</td><td class="num">{{ rupiah($p->amount) }}</td></tr>@endforeach
      @foreach($closing->shift?->cashMovements ?? [] as $m)<tr><td>{{ $m->created_at->format('H:i') }}</td><td colspan="2">{{ ['income'=>'Penerimaan lain','expense'=>'Pengeluaran','deposit'=>'Setoran'][$m->type] }} · {{ $m->note }}</td><td></td><td class="num">{{ rupiah($m->amount) }}</td></tr>@endforeach
    </tbody></table></div></div>
  </div>
  <div class="stack">
    @if(auth()->user()->hasAbility('closing.approve'))
      @if($closing->approval_status === 'pending' && $closing->status === 'final')
      <form method="POST" action="{{ route('closings.approve', $closing) }}" class="card card-pad">@csrf
        <h3 style="margin-bottom:8px">Persetujuan</h3>
        <div class="field"><input name="note" placeholder="Catatan (opsional)"></div>
        <div class="row"><button name="decision" value="approve" class="btn btn-accent">Setujui</button><button name="decision" value="reject" class="btn btn-danger">Tolak</button></div>
      </form>
      @endif
      @if($closing->status === 'final')
      <details class="card card-pad disclose"><summary>Buka ulang closing</summary>
        <form method="POST" action="{{ route('closings.reopen', $closing) }}" style="margin-top:10px">@csrf
          <p class="muted">Shift dibuka kembali agar kasir dapat closing ulang. Arsip ini tetap tersimpan dan tindakan dicatat di audit.</p>
          <div class="field"><label>Alasan (wajib)</label><textarea name="reason" rows="2" required minlength="5"></textarea></div>
          <button class="btn btn-danger">Buka ulang</button></form>
      </details>
      @endif
    @endif
    <div class="card"><div class="card-head"><h3>Jejak audit</h3></div>
      @forelse($audits as $a)<div style="padding:10px 18px;border-bottom:1px solid var(--border)"><b>{{ $a->action }}</b><br><small>{{ $a->created_at->format('d/m/Y H:i') }} · {{ $a->user?->name }}{{ $a->reason ? ' · '.$a->reason : '' }}</small></div>
      @empty <div class="empty">—</div>@endforelse
    </div>
  </div>
</div>
@endsection
