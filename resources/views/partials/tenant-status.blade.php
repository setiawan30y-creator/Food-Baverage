@php $st = $t->effectiveStatus(); $tone = ['trial' => 'info', 'active' => 'ok', 'past_due' => 'warn', 'expired' => 'danger', 'suspended' => 'danger'][$st] ?? 'muted'; @endphp
<span class="badge badge-{{ $tone }}">{{ $t->statusLabel() }}</span>
