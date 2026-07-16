<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Categorías de egreso comunes (MoneyWiz/YNAB/Mint) que faltaban en el
     * catálogo inicial. Idempotente: solo inserta las que no existan por
     * nombre, para correr con seguridad sobre datos existentes.
     */
    private array $categories = [
        ['name' => 'Impuestos',            'color' => '#b91c1c', 'icon' => 'receipt',   'children' => ['SAT / Declaraciones', 'Contador']],
        ['name' => 'Viajes',               'color' => '#0ea5e9', 'icon' => 'plane',     'children' => ['Vuelos', 'Hospedaje']],
        ['name' => 'Regalos y donaciones', 'color' => '#e11d48', 'icon' => 'gift',      'children' => []],
        ['name' => 'Cuidado personal',     'color' => '#14b8a6', 'icon' => 'scissors',  'children' => []],
        ['name' => 'Mascotas',             'color' => '#a16207', 'icon' => 'paw-print', 'children' => []],
        ['name' => 'Familia',              'color' => '#7c3aed', 'icon' => 'users',     'children' => []],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->categories as $data) {
            $exists = DB::table('categories')
                ->where('name', $data['name'])
                ->where('kind', 'expense')
                ->whereNull('parent_id')
                ->exists();

            if ($exists) {
                continue;
            }

            $parentId = DB::table('categories')->insertGetId([
                'name'       => $data['name'],
                'kind'       => 'expense',
                'color'      => $data['color'],
                'icon'       => $data['icon'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($data['children'] as $child) {
                DB::table('categories')->insert([
                    'parent_id'  => $parentId,
                    'name'       => $child,
                    'kind'       => 'expense',
                    'color'      => $data['color'],
                    'icon'       => $data['icon'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Solo elimina las categorías agregadas que no tengan movimientos
        foreach ($this->categories as $data) {
            $parent = DB::table('categories')
                ->where('name', $data['name'])
                ->where('kind', 'expense')
                ->whereNull('parent_id')
                ->first();

            if (! $parent) {
                continue;
            }

            $ids = DB::table('categories')
                ->where('id', $parent->id)
                ->orWhere('parent_id', $parent->id)
                ->pluck('id');

            $used = DB::table('transactions')->whereIn('category_id', $ids)->exists();

            if (! $used) {
                DB::table('categories')->whereIn('id', $ids)->delete();
            }
        }
    }
};
