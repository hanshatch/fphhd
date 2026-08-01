<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Subcategorías de la categoría de ingreso «Docencia»: las escuelas donde
     * se imparte clase. Idempotente: solo inserta las que falten por nombre.
     */
    private array $children = ['TEC', 'ITAM', 'Anáhuac'];

    public function up(): void
    {
        $parent = DB::table('categories')
            ->where('name', 'Docencia')
            ->where('kind', 'income')
            ->whereNull('parent_id')
            ->first();

        if (! $parent) {
            return;
        }

        $now = now();

        foreach ($this->children as $child) {
            $exists = DB::table('categories')
                ->where('parent_id', $parent->id)
                ->where('name', $child)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('categories')->insert([
                'parent_id'  => $parent->id,
                'name'       => $child,
                'kind'       => 'income',
                'color'      => $parent->color,
                'icon'       => $parent->icon,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        $parent = DB::table('categories')
            ->where('name', 'Docencia')
            ->where('kind', 'income')
            ->whereNull('parent_id')
            ->first();

        if (! $parent) {
            return;
        }

        // Solo elimina las que no tengan movimientos asociados
        $ids = DB::table('categories')
            ->where('parent_id', $parent->id)
            ->whereIn('name', $this->children)
            ->pluck('id');

        foreach ($ids as $id) {
            if (! DB::table('transactions')->where('category_id', $id)->exists()) {
                DB::table('categories')->where('id', $id)->delete();
            }
        }
    }
};
