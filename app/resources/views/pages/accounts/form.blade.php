<x-app-layout :title="$account->exists ? 'Editar cuenta' : 'Nueva cuenta'">
    <div class="max-w-lg mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('accounts.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">
                {{ $account->exists ? 'Editar cuenta' : 'Nueva cuenta' }}
            </h1>
        </div>

        <form method="POST" action="{{ $account->exists ? route('accounts.update', $account) : route('accounts.store') }}" class="space-y-5">
            @csrf
            @if($account->exists) @method('PATCH') @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
                <input type="text" name="name" value="{{ old('name', $account->name) }}" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                    placeholder="ej. Banamex débito">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo *</label>
                    <select name="type" required class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        @foreach(['debit' => 'Débito', 'credit' => 'Tarjeta crédito', 'savings' => 'Caja de ahorro', 'investment' => 'Inversión', 'cash' => 'Efectivo'] as $val => $label)
                            <option value="{{ $val }}" {{ old('type', $account->type) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('type')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Institución *</label>
                    <select name="institution" required class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                        @foreach(['banamex' => 'Banamex', 'mercadopago' => 'MercadoPago', 'nu' => 'Nu', 'revolut' => 'Revolut', 'other' => 'Otra'] as $val => $label)
                            <option value="{{ $val }}" {{ old('institution', $account->institution) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Saldo inicial *</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                    <input type="number" name="initial_balance" step="0.01" min="0"
                        value="{{ old('initial_balance', $account->initial_balance ?? '0') }}" required
                        inputmode="decimal"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 pl-7 pr-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
                </div>
                @error('initial_balance')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">APR nominal anual (%)</label>
                <input type="number" name="invest_apr" step="0.01" min="0" max="100"
                    value="{{ old('invest_apr', $account->invest_apr) }}"
                    inputmode="decimal"
                    placeholder="Solo para cajas de ahorro, ej. 13.00"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                <div class="flex items-center gap-3">
                    <input type="color" name="color" value="{{ old('color', $account->color ?? '#6366f1') }}"
                        class="h-10 w-16 rounded-lg border border-gray-300 dark:border-gray-700 cursor-pointer">
                    <span class="text-xs text-gray-500">Elige un color para identificar la cuenta</span>
                </div>
            </div>

            @if($account->exists)
            <div class="flex items-center gap-3">
                <input type="checkbox" name="is_active" id="is_active" value="1" {{ $account->is_active ? 'checked' : '' }}
                    class="w-4 h-4 text-indigo-600 rounded border-gray-300">
                <label for="is_active" class="text-sm text-gray-700 dark:text-gray-300">Cuenta activa</label>
            </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                <textarea name="notes" rows="2" maxlength="500"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                    placeholder="Opcional">{{ old('notes', $account->notes) }}</textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('accounts.index') }}" class="flex-1 text-center px-4 py-2.5 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                    Cancelar
                </a>
                <button type="submit" class="flex-1 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition-colors">
                    {{ $account->exists ? 'Guardar cambios' : 'Crear cuenta' }}
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
