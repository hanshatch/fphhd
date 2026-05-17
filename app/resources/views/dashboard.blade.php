<x-app-layout title="Panel">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[#373737] dark:text-white font-['Nunito']">
            Bienvenido, <span class="text-[#76a72b]">Hans</span> 👋
        </h1>
        <p class="text-[#878787] text-sm mt-1">{{ now()->translatedFormat('l, d \d\e F \d\e Y') }}</p>
    </div>

    {{-- Patrimonio neto --}}
    @php
    $service = app(\App\Services\AccountService::class);
    $netWorth = $service->netWorth();
    $accounts = \App\Models\Account::where('is_active', true)->get()
        ->map(fn($a) => ['account' => $a, 'balance' => $service->balance($a)]);
    @endphp

    <x-card class="p-6 mb-4 bg-[#373737] dark:bg-[#222] border-0">
        <p class="text-white/50 text-xs font-semibold uppercase tracking-widest mb-1">Patrimonio neto</p>
        <p class="text-4xl font-bold text-white font-['Nunito']">
            ${{ number_format((float)$netWorth, 2) }}
            <span class="text-white/40 text-base font-normal">MXN</span>
        </p>
        <div class="flex gap-4 mt-3">
            @php
            $assets = $accounts->filter(fn($i) => !$i['account']->isCredit())->sum(fn($i) => (float)$i['balance']);
            $debts  = $accounts->filter(fn($i) => $i['account']->isCredit())->sum(fn($i) => (float)$i['balance']);
            @endphp
            <div>
                <p class="text-white/40 text-[10px] uppercase tracking-wider">Activos</p>
                <p class="text-[#76a72b] font-bold text-sm font-['Nunito']">${{ number_format($assets, 2) }}</p>
            </div>
            <div>
                <p class="text-white/40 text-[10px] uppercase tracking-wider">Tarjetas</p>
                <p class="text-red-400 font-bold text-sm font-['Nunito']">${{ number_format($debts, 2) }}</p>
            </div>
        </div>
    </x-card>

    {{-- Cuentas --}}
    @if($accounts->isNotEmpty())
    <h2 class="text-sm font-bold text-[#878787] uppercase tracking-wider mb-3">Saldos por cuenta</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
        @foreach($accounts as $item)
        @php $acc = $item['account']; $bal = $item['balance']; @endphp
        <x-card class="p-4 flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
                 style="background-color: {{ $acc->color }}22">
                <div class="w-3.5 h-3.5 rounded-full" style="background-color: {{ $acc->color }}"></div>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-[#373737] dark:text-white text-sm truncate font-['Nunito']">{{ $acc->name }}</p>
                <p class="text-[10px] text-[#ababab] capitalize">{{ $acc->institution }}</p>
            </div>
            <div class="text-right">
                <p class="font-bold text-sm {{ $acc->isCredit() ? 'text-red-500' : 'text-[#373737] dark:text-white' }} font-['Nunito']">
                    ${{ number_format((float)$bal, 2) }}
                </p>
            </div>
        </x-card>
        @endforeach
    </div>
    @endif

    {{-- Últimos movimientos --}}
    @php $recent = \App\Models\Transaction::with('account','category')->orderBy('date','desc')->orderBy('id','desc')->limit(5)->get(); @endphp
    @if($recent->isNotEmpty())
    <h2 class="text-sm font-bold text-[#878787] uppercase tracking-wider mb-3">Últimos movimientos</h2>
    <x-card>
        <div class="divide-y divide-[#efeded] dark:divide-white/10">
            @php
            $cfg = ['income'=>['text-[#76a72b]','+'],'interest'=>['text-[#76a72b]','+'],'expense'=>['text-red-500','-'],'fee'=>['text-red-500','-'],'transfer'=>['text-[#878787]','']];
            @endphp
            @foreach($recent as $tx)
            @php [$color,$sign] = $cfg[$tx->type] ?? ['text-[#878787]','']; @endphp
            <a href="{{ route('transactions.edit', $tx) }}" class="flex items-center gap-3 p-4 hover:bg-[#efeded]/50 dark:hover:bg-white/5 transition-colors">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-[#373737] dark:text-white truncate">{{ $tx->description ?: ucfirst($tx->type) }}</p>
                    <p class="text-xs text-[#ababab]">{{ $tx->date->translatedFormat('d M') }} · {{ $tx->account->name }}</p>
                </div>
                <span class="font-bold text-sm {{ $color }} font-['Nunito']">{{ $sign }}${{ number_format((float)$tx->amount, 2) }}</span>
            </a>
            @endforeach
        </div>
        <div class="p-4 border-t border-[#efeded] dark:border-white/10">
            <a href="{{ route('transactions.index') }}" class="text-sm text-[#76a72b] font-semibold hover:underline">Ver todos los movimientos →</a>
        </div>
    </x-card>
    @else
    <x-card class="text-center py-12">
        <p class="text-[#878787]">Aún no hay movimientos.</p>
        <x-btn href="{{ route('transactions.create') }}" class="mt-3">Registrar primero</x-btn>
    </x-card>
    @endif
</x-app-layout>
