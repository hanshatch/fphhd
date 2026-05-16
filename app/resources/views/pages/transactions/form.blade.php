<x-app-layout :title="$transaction->exists ? 'Editar movimiento' : 'Nuevo movimiento'">
    <div class="max-w-lg mx-auto" x-data="transactionForm('{{ old('type', $transaction->type ?? 'expense') }}')">

        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('transactions.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                {{ $transaction->exists ? 'Editar movimiento' : 'Nuevo movimiento' }}
            </h1>
        </div>

        <form method="POST" action="{{ $transaction->exists ? route('transactions.update', $transaction) : route('transactions.store') }}" class="space-y-5">
            @csrf
            @if($transaction->exists) @method('PATCH') @endif

            {{-- Selector de tipo --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Tipo *</label>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    @foreach([
                        'expense'  => ['💸', 'Egreso'],
                        'income'   => ['💰', 'Ingreso'],
                        'transfer' => ['🔄', 'Transferencia'],
                        'interest' => ['📈', 'Interés'],
                    ] as $val => [$emoji, $label])
                    <label class="cursor-pointer">
                        <input type="radio" name="type" value="{{ $val }}" x-model="type" class="sr-only"
                            {{ old('type', $transaction->type ?? 'expense') === $val ? 'checked' : '' }}>
                        <div :class="type === '{{ $val }}' ? 'bg-indigo-50 dark:bg-indigo-900/30 border-indigo-500 text-indigo-700 dark:text-indigo-300' : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400'"
                             class="border-2 rounded-xl p-3 text-center transition-colors">
                            <div class="text-xl mb-1">{{ $emoji }}</div>
                            <div class="text-xs font-medium">{{ $label }}</div>
                        </div>
                    </label>
                    @endforeach
                </div>
                @error('type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Monto --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Monto *</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-medium">$</span>
                    <input type="number" name="amount" step="0.01" min="0.01" required inputmode="decimal"
                        value="{{ old('amount', $transaction->amount) }}"
                        class="w-full rounded-xl border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-8 pr-16 py-3 text-xl font-semibold text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                        placeholder="0.00" autofocus>
                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">MXN</span>
                </div>
                @error('amount')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Cuenta origen --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    <span x-text="type === 'transfer' ? 'Cuenta origen *' : 'Cuenta *'"></span>
                </label>
                <select name="account_id" required class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    <option value="">Selecciona una cuenta</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" {{ old('account_id', $transaction->account_id) == $account->id ? 'selected' : '' }}>
                            {{ $account->name }} ({{ ucfirst($account->institution) }})
                        </option>
                    @endforeach
                </select>
                @error('account_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Cuenta destino (solo transferencias) --}}
            <div x-show="type === 'transfer'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cuenta destino *</label>
                <select name="counterparty_account_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    <option value="">Selecciona cuenta destino</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" {{ old('counterparty_account_id', $transaction->counterparty_account_id) == $account->id ? 'selected' : '' }}>
                            {{ $account->name }}
                        </option>
                    @endforeach
                </select>
                @error('counterparty_account_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Categoría (no en transferencias) --}}
            <div x-show="type !== 'transfer' && type !== 'interest'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Categoría</label>
                <select name="category_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    <option value="">Sin categoría</option>
                    @foreach($categories->whereNull('parent_id') as $cat)
                        @if(($type === 'income' && $cat->kind === 'income') || ($type !== 'income' && $cat->kind === 'expense') || true)
                        <optgroup label="{{ $cat->name }}">
                            <option value="{{ $cat->id }}" {{ old('category_id', $transaction->category_id) == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                            @foreach($cat->children as $child)
                                <option value="{{ $child->id }}" {{ old('category_id', $transaction->category_id) == $child->id ? 'selected' : '' }}>
                                    &nbsp;&nbsp;{{ $child->name }}
                                </option>
                            @endforeach
                        </optgroup>
                        @endif
                    @endforeach
                </select>
            </div>

            {{-- Fuente (solo ingresos) --}}
            <div x-show="type === 'income'" x-cloak>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fuente de ingreso</label>
                <select name="source_id" class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                    <option value="">Sin fuente</option>
                    @foreach($sources as $source)
                        <option value="{{ $source->id }}" {{ old('source_id', $transaction->source_id) == $source->id ? 'selected' : '' }}>
                            {{ $source->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Fecha --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fecha *</label>
                <input type="date" name="date" required
                    value="{{ old('date', $transaction->date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                @error('date')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            {{-- Descripción --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descripción</label>
                <input type="text" name="description" maxlength="500"
                    value="{{ old('description', $transaction->description) }}"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                    placeholder="Opcional">
            </div>

            {{-- Botones --}}
            <div class="flex gap-3 pt-2">
                <a href="{{ route('transactions.index') }}" class="flex-1 text-center px-4 py-2.5 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">
                    Cancelar
                </a>
                @if(!$transaction->exists)
                <button type="submit" name="save_and_new" value="1"
                    class="px-4 py-2.5 border border-indigo-300 text-indigo-600 dark:border-indigo-700 dark:text-indigo-400 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20 text-sm">
                    + otro
                </button>
                @endif
                <button type="submit" class="flex-1 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg">
                    {{ $transaction->exists ? 'Guardar' : 'Registrar' }}
                </button>
            </div>
        </form>
    </div>

    <script>
    function transactionForm(initialType) {
        return { type: initialType }
    }
    </script>
</x-app-layout>
