<x-app-layout title="Cuentas">
    <x-page-header title="Cuentas" action-route="{{ route('accounts.create') }}" action-label="Nueva cuenta" />

    @if($accounts->isEmpty())
    <x-card class="text-center py-16">
        <svg class="mx-auto w-12 h-12 text-[#ababab] mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        <p class="text-[#878787] font-medium">Aún no tienes cuentas</p>
        <p class="text-[#ababab] text-sm mt-1">Agrega tus cuentas bancarias, cajas de ahorro y tarjetas</p>
        <x-btn href="{{ route('accounts.create') }}" class="mt-4">Crear primera cuenta</x-btn>
    </x-card>
    @else
    <div class="space-y-3">
        @php
        $typeLabels = ['debit' => 'Débito', 'credit' => 'TDC', 'savings' => 'Ahorro', 'investment' => 'Inversión', 'cash' => 'Efectivo'];
        $instLabels = ['banamex' => 'Banamex', 'mercadopago' => 'MercadoPago', 'nu' => 'Nu', 'revolut' => 'Revolut', 'amex' => 'American Express', 'other' => 'Otra'];
        @endphp
        @foreach($accounts as $item)
        @php $account = $item['account']; $balance = $item['balance']; @endphp
        <x-card class="p-4 flex items-center gap-4">
            {{-- Logo o color --}}
            <div class="w-11 h-11 rounded-xl flex-shrink-0 flex items-center justify-center overflow-hidden"
                 style="background-color: {{ $account->color }}22">
                @if($account->logo_path)
                    <img src="{{ $account->logoUrl() }}" alt="{{ $account->name }}" class="w-8 h-8 object-contain">
                @else
                    <div class="w-4 h-4 rounded-full" style="background-color: {{ $account->color }}"></div>
                @endif
            </div>

            {{-- Info --}}
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-[#373737] dark:text-white truncate font-['Nunito']">{{ $account->name }}</span>
                    @if(!$account->is_active)
                        <span class="text-[10px] bg-[#efeded] dark:bg-white/10 text-[#878787] px-2 py-0.5 rounded-full font-medium">Inactiva</span>
                    @endif
                </div>
                <span class="text-xs text-[#878787]">{{ $instLabels[$account->institution] ?? $account->institution }} · {{ $typeLabels[$account->type] ?? $account->type }}</span>
            </div>

            {{-- Balance --}}
            <div class="text-right">
                <div class="font-bold font-['Nunito'] {{ $account->isCredit() ? 'text-red-500' : 'text-[#373737] dark:text-white' }}">
                    ${{ number_format((float)$balance, 2) }}
                </div>
                <div class="text-[10px] text-[#ababab] uppercase tracking-wider">MXN</div>
            </div>

            {{-- Actions --}}
            <div class="flex items-center gap-1 flex-shrink-0">
                <a href="{{ route('accounts.edit', $account) }}"
                   class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-[#76a72b] hover:bg-[#76a72b]/10 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </a>
                <form method="POST" action="{{ route('accounts.destroy', $account) }}"
                      onsubmit="return confirm('¿Eliminar la cuenta «{{ addslashes($account->name) }}»?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="w-8 h-8 flex items-center justify-center text-[#ababab] hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-500/10 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    </button>
                </form>
            </div>
        </x-card>
        @endforeach
    </div>
    @endif
</x-app-layout>
