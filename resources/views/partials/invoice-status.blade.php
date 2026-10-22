@php $tone = ['unpaid' => 'warn', 'waiting' => 'info', 'paid' => 'ok', 'void' => 'muted'][$i->status] ?? 'muted'; @endphp
<span class="badge badge-{{ $tone }}">{{ $i->statusLabel() }}</span>
