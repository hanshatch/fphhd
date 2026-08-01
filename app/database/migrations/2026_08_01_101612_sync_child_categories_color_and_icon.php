<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Alinea las subcategorías existentes con el color, icono y tipo de su
     * padre. Venían desalineadas de altas manuales (p. ej. hijas moradas
     * colgando de «Salud», que es roja).
     */
    public function up(): void
    {
        $parents = DB::table('categories')->whereNull('parent_id')->get();

        foreach ($parents as $parent) {
            DB::table('categories')
                ->where('parent_id', $parent->id)
                ->update([
                    'color'      => $parent->color,
                    'icon'       => $parent->icon,
                    'kind'       => $parent->kind,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // No hay vuelta atrás: los colores previos de cada hija no se guardaron.
    }
};
