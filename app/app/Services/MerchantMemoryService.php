<?php

namespace App\Services;

use App\Models\MerchantRule;
use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * Memoria de comercios: aprende qué categoría usa Hans para cada comercio
 * y la propone la siguiente vez que aparece (al importar capturas, etc.).
 *
 * La llave es la descripción normalizada: minúsculas, sin acentos, sin
 * dígitos ni signos, y solo las primeras palabras. Así «OXXO», «Oxxo Agua»
 * y «OXXO 0158 DF» caen en la misma regla.
 */
class MerchantMemoryService
{
    private const KEY_WORDS = 3;

    /** Llave normalizada, o null si no queda nada útil */
    public function key(?string $description): ?string
    {
        $text = Str::ascii(mb_strtolower(trim((string) $description)));
        $text = preg_replace('/[^a-z\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', trim($text));

        if ($text === '') {
            return null;
        }

        // Palabras de 1-2 letras (DF, CD, MX, V) son ruido de sucursal, no del comercio
        $words = array_filter(explode(' ', $text), fn ($w) => strlen($w) > 2);
        $key   = implode(' ', array_slice(array_values($words), 0, self::KEY_WORDS));

        return $key !== '' ? Str::limit($key, 120, '') : null;
    }

    /**
     * Categoría aprendida para una descripción y tipo, o null.
     * Primero busca una regla; si no hay, mira el historial de movimientos
     * con la misma llave y toma la categoría más usada.
     */
    public function suggest(?string $description, string $type): ?int
    {
        $key = $this->key($description);

        if ($key === null) {
            return null;
        }

        $rule = $this->rule($key, $type);

        if ($rule) {
            return $rule->category_id;
        }

        return $this->fromHistory($key, $type);
    }

    /** Fuente aprendida para un ingreso con esa descripción (ej. «Abono/Nomina» → Tec), o null */
    public function suggestSource(?string $description): ?int
    {
        $key = $this->key($description);

        return $key === null ? null : $this->rule($key, 'income')?->source_id;
    }

    /**
     * Regla exacta o, si no hay, la más larga que sea el inicio de la llave:
     * «abono nomina» también aplica a «abono nomina quincena».
     */
    private function rule(string $key, string $type): ?MerchantRule
    {
        return MerchantRule::where('type', $type)
            ->where('merchant_key', 'like', Str::before($key, ' ') . '%')
            ->get()
            ->filter(fn (MerchantRule $r) => $key === $r->merchant_key || str_starts_with($key, $r->merchant_key . ' '))
            ->sortByDesc(fn (MerchantRule $r) => strlen($r->merchant_key))
            ->first();
    }

    /** Registra (o refuerza) la regla comercio → categoría */
    public function learn(Transaction $transaction): void
    {
        if (! $transaction->category_id || ! in_array($transaction->type, ['expense', 'income'], true)) {
            return;
        }

        $key = $this->key($transaction->description);

        if ($key === null) {
            return;
        }

        $rule = MerchantRule::firstOrNew(['merchant_key' => $key]);

        // Si Hans cambió de categoría para este comercio, la regla sigue su última decisión
        $rule->fill([
            'sample'       => Str::limit((string) $transaction->description, 200, ''),
            'type'         => $transaction->type,
            'category_id'  => $transaction->category_id,
            // La fuente solo se pisa con otra fuente: un ingreso sin fuente no borra lo aprendido
            'source_id'    => $transaction->type === 'income' ? ($transaction->source_id ?: $rule->source_id) : null,
            'hits'         => $rule->exists ? $rule->hits + 1 : 1,
            'last_used_at' => now(),
        ])->save();
    }

    private function fromHistory(string $key, string $type): ?int
    {
        // Primer término de la llave como filtro barato en SQL; el resto se afina en PHP
        $first = Str::before($key, ' ');

        $matches = Transaction::query()
            ->where('type', $type)
            ->whereNotNull('category_id')
            ->where('description', 'like', '%' . $first . '%')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['description', 'category_id'])
            ->filter(fn ($t) => $this->key($t->description) === $key);

        if ($matches->isEmpty()) {
            return null;
        }

        return (int) $matches->countBy('category_id')->sortDesc()->keys()->first();
    }
}
