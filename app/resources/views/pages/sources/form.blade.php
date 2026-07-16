<x-app-layout :title="$source->exists ? 'Editar fuente' : 'Nueva fuente'">
    <div class="max-w-lg mx-auto">
        <div class="flex items-center gap-3 mb-6">
            <a href="{{ route('sources.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="text-xl font-bold text-gray-900 dark:text-white">{{ $source->exists ? 'Editar fuente' : 'Nueva fuente' }}</h1>
        </div>

        <form method="POST" action="{{ $source->exists ? route('sources.update', $source) : route('sources.store') }}" class="space-y-5">
            @csrf
            @if($source->exists) @method('PATCH') @endif

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nombre *</label>
                <input type="text" name="name" value="{{ old('name', $source->name) }}" required
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]"
                    placeholder="ej. UNAM, Agencia Hans, Curso Python">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo *</label>
                <select name="kind" required class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">
                    @foreach(['agency' => 'Agencia', 'university' => 'Universidad', 'training' => 'Capacitación', 'other' => 'Otro'] as $val => $label)
                        <option value="{{ $val }}" {{ old('kind', $source->kind) === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Notas</label>
                <textarea name="notes" rows="2" maxlength="500"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2.5 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-[#76a72b]">{{ old('notes', $source->notes) }}</textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <a href="{{ route('sources.index') }}" class="flex-1 text-center px-4 py-2.5 border border-gray-300 dark:border-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800">Cancelar</a>
                <button type="submit" class="flex-1 px-4 py-2.5 bg-[#76a72b] hover:bg-[#659220] text-white font-medium rounded-lg shadow-sm transition-colors">{{ $source->exists ? 'Guardar' : 'Crear fuente' }}</button>
            </div>
        </form>
    </div>
</x-app-layout>
