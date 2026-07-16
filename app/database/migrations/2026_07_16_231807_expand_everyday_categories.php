<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Expansión del catálogo con subcategorías de consumo diario según los
     * catálogos estándar de MoneyWiz/YNAB/Mint. Idempotente: agrega hijos a
     * padres existentes (buscados por nombre) solo si no existen, y el padre
     * nuevo "Trabajo" para gastos de freelancer.
     */
    private array $newChildren = [
        'Alimentación'         => ['Vinos y licores'],
        'Transporte'           => ['Estacionamiento', 'Lavado de auto'],
        'Hogar'                => ['Mantenimiento del hogar', 'Muebles y decoración', 'Artículos de limpieza', 'Lavandería y tintorería'],
        'Salud'                => ['Dentista', 'Lentes y óptica', 'Terapia'],
        'Entretenimiento'      => ['Bares y antros', 'Hobbies', 'Membresías'],
        'Ropa'                 => ['Calzado', 'Accesorios'],
        'Cuidado personal'     => ['Estética y barbería', 'Cosméticos', 'Spa y masajes'],
        'Mascotas'             => ['Veterinario', 'Alimento y accesorios'],
        'Viajes'               => ['Comidas en viaje', 'Tours y actividades'],
        'Regalos y donaciones' => ['Cumpleaños', 'Donaciones'],
        'Familia'              => ['Apoyo familiar'],
        'Impuestos'            => ['Predial y tenencia'],
        'Finanzas'             => ['Créditos y préstamos'],
    ];

    private array $newParents = [
        ['name' => 'Trabajo', 'color' => '#475569', 'icon' => 'briefcase', 'children' => ['Papelería', 'Envíos y mensajería', 'Publicidad', 'Coworking / Oficina']],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->newChildren as $parentName => $children) {
            $parent = DB::table('categories')
                ->where('name', $parentName)
                ->where('kind', 'expense')
                ->whereNull('parent_id')
                ->first();

            if (! $parent) {
                continue;
            }

            foreach ($children as $child) {
                $exists = DB::table('categories')
                    ->where('name', $child)
                    ->where('parent_id', $parent->id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('categories')->insert([
                    'parent_id'  => $parent->id,
                    'name'       => $child,
                    'kind'       => 'expense',
                    'color'      => $parent->color,
                    'icon'       => $parent->icon,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach ($this->newParents as $data) {
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
        // Elimina solo lo agregado aquí y solo si no tiene movimientos
        foreach ($this->newChildren as $parentName => $children) {
            $parent = DB::table('categories')
                ->where('name', $parentName)
                ->where('kind', 'expense')
                ->whereNull('parent_id')
                ->first();

            if (! $parent) {
                continue;
            }

            foreach ($children as $child) {
                $row = DB::table('categories')
                    ->where('name', $child)
                    ->where('parent_id', $parent->id)
                    ->first();

                if ($row && ! DB::table('transactions')->where('category_id', $row->id)->exists()) {
                    DB::table('categories')->where('id', $row->id)->delete();
                }
            }
        }

        foreach ($this->newParents as $data) {
            $parent = DB::table('categories')
                ->where('name', $data['name'])
                ->where('kind', 'expense')
                ->whereNull('parent_id')
                ->first();

            if (! $parent) {
                continue;
            }

            $ids  = DB::table('categories')->where('id', $parent->id)->orWhere('parent_id', $parent->id)->pluck('id');
            $used = DB::table('transactions')->whereIn('category_id', $ids)->exists();

            if (! $used) {
                DB::table('categories')->whereIn('id', $ids)->delete();
            }
        }
    }
};
