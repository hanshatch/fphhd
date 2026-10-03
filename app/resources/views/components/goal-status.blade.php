{{-- Estado de una meta: siempre ícono + texto, nunca solo color --}}
@props(['status'])
@php
$cfg = [
    'on_track'  => ['Al corriente', 'bg-[#76a72b]/10 text-positive',               'M5 13l4 4L19 7'],
    'funded'    => ['Completa',     'bg-[#76a72b]/10 text-positive',               'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    'behind'    => ['Atrasada',     'bg-amber-50 dark:bg-amber-500/10 text-warn',  'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
    'overdue'   => ['Vencida',      'bg-red-50 dark:bg-red-500/10 text-negative',  'M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'],
    'completed' => ['Realizada',    'bg-[#efeded] dark:bg-white/10 text-[#878787]', 'M5 13l4 4L19 7'],
][$status] ?? ['—', 'bg-[#efeded] text-[#878787]', 'M5 12h14'];
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-bold {$cfg[1]}"]) }}>
    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $cfg[2] }}"/></svg>
    {{ $cfg[0] }}
</span>
