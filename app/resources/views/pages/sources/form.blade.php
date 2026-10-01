<x-app-layout :title="$source->exists ? 'Editar fuente' : 'Nueva fuente'">
    <div class="max-w-lg mx-auto">
        <x-page-header :title="$source->exists ? 'Editar fuente' : 'Nueva fuente'" :back="route('sources.index')" />

        <x-card class="p-6">
        <form method="POST" action="{{ $source->exists ? route('sources.update', $source) : route('sources.store') }}" class="space-y-5">
            @csrf
            @if($source->exists) @method('PATCH') @endif

            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Nombre <span class="text-positive">*</span></label>
                <input type="text" name="name" value="{{ old('name', $source->name) }}" required @unless($source->exists) autofocus @endunless
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition"
                    placeholder="ej. UNAM, Agencia Hans, Curso Python">
                @error('name')<p class="mt-1.5 text-xs text-negative">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Tipo <span class="text-positive">*</span></label>
                <select name="kind" required class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">
                    @foreach(['agency' => 'Agencia', 'university' => 'Universidad', 'training' => 'Capacitación', 'other' => 'Otro'] as $val => $label)
                        <option value="{{ $val }}" {{ old('kind', $source->kind) === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-[#373737] dark:text-white mb-1.5">Notas</label>
                <textarea name="notes" rows="2" maxlength="500"
                    class="w-full rounded-xl border border-[#ababab]/40 bg-[#efeded]/50 dark:bg-white/5 px-4 py-3 text-[#373737] dark:text-white placeholder-[#ababab] focus:outline-none focus:ring-2 focus:ring-[#76a72b] transition">{{ old('notes', $source->notes) }}</textarea>
            </div>

            <div class="flex gap-3 pt-2">
                <x-btn variant="secondary" href="{{ route('sources.index') }}" class="flex-1">Cancelar</x-btn>
                <x-btn type="submit" class="flex-1">{{ $source->exists ? 'Guardar' : 'Crear fuente' }}</x-btn>
            </div>
        </form>
        </x-card>
    </div>
</x-app-layout>
