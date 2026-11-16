{{-- Renglón de un cargo por aplicar este mes. $state: 'overdue' | 'upcoming' --}}
@php
    $days = (int) now()->startOfDay()->diffInDays($charge->next_application_date->copy()->startOfDay(), false);
    $when = match (true) {
        $days < -1  => 'venció el ' . $charge->next_application_date->translatedFormat('j M') . ' · hace ' . abs($days) . ' días',
        $days === -1 => 'venció ayer',
        $days === 0 => 'vence hoy',
        $days === 1 => 'vence mañana',
        default     => 'vence el ' . $charge->next_application_date->translatedFormat('j M') . ' · en ' . $days . ' días',
    };
    $late = $state === 'overdue';
@endphp
<li class="flex items-start gap-3 px-4 py-3">
    <span class="flex-shrink-0 mt-1.5 w-2.5 h-2.5 rounded-full {{ $late ? 'bg-red-500' : ($days <= 3 ? 'bg-amber-400' : 'bg-[#ababab]') }}" aria-hidden="true"></span>

    <div class="flex-1 min-w-0">
        <div class="flex items-baseline justify-between gap-3">
            <p class="text-sm font-semibold text-[#373737] dark:text-white truncate">
                {{ $charge->name }}
                @if($charge->is_msi)
                    <span class="ml-1 text-[11px] font-bold text-blue-600 dark:text-blue-400 tabular-nums">{{ $charge->applied_installments + 1 }}/{{ $charge->total_installments }}</span>
                @endif
            </p>
            <p class="flex-shrink-0 text-sm font-bold tabular-nums {{ $charge->type === 'expense' ? 'text-negative' : 'text-positive' }}">
                {{ $charge->type === 'expense' ? '−' : '+' }}{{ format_currency($charge->amount) }}
            </p>
        </div>
        <p class="mt-0.5 text-xs truncate {{ $late ? 'text-negative font-semibold' : 'text-[#878787]' }}">
            {{ ucfirst($when) }} <span class="font-normal text-[#878787]">· {{ $charge->account->name }}</span>
        </p>

        <div class="mt-2 flex items-center gap-2">
            <a href="{{ route('recurring.apply.show', $charge) }}"
               class="inline-flex items-center justify-center min-h-[44px] px-4 rounded-lg text-xs font-semibold transition-colors
                      {{ $late || $days === 0 ? 'bg-[#76a72b] text-white hover:bg-[#6a9626]' : 'border border-[#76a72b]/40 text-positive hover:bg-[#76a72b]/10' }}">
                {{ $late || $days === 0 ? 'Aplicar' : 'Aplicar antes' }}
            </a>
            @unless($charge->is_msi)
            <form method="POST" action="{{ route('recurring.skip', $charge) }}"
                  data-confirm-title="Omitir este cargo"
                  data-confirm="¿Omitir «{{ $charge->name }}» de {{ $charge->next_application_date->translatedFormat('F Y') }}? No se creará movimiento y el siguiente será el {{ $charge->calculateNextDate($charge->next_application_date)->translatedFormat('j M Y') }}."
                  data-confirm-label="Omitir">
                @csrf
                <button type="submit" class="inline-flex items-center min-h-[44px] px-3 rounded-lg text-xs font-semibold text-[#878787] hover:text-[#373737] dark:hover:text-white hover:bg-[#efeded] dark:hover:bg-white/10 transition-colors">
                    Omitir
                </button>
            </form>
            @endunless
        </div>
    </div>
</li>
