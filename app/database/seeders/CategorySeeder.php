<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $expenses = [
            ['name' => 'Alimentación',   'color' => '#f97316', 'icon' => 'utensils',    'children' => ['Súper', 'Restaurantes', 'Comida a domicilio', 'Cafeterías']],
            ['name' => 'Transporte',     'color' => '#3b82f6', 'icon' => 'car',         'children' => ['Gasolina', 'Uber/Didi', 'Casetas', 'Mantenimiento auto', 'Transporte público']],
            ['name' => 'Hogar',          'color' => '#8b5cf6', 'icon' => 'home',        'children' => ['Renta', 'Luz', 'Agua', 'Gas', 'Internet', 'Teléfono']],
            ['name' => 'Salud',          'color' => '#ef4444', 'icon' => 'heart-pulse', 'children' => ['Médico', 'Medicamentos', 'Gimnasio']],
            ['name' => 'Educación',      'color' => '#06b6d4', 'icon' => 'graduation-cap', 'children' => ['Cursos', 'Libros', 'Suscripciones educativas']],
            ['name' => 'Entretenimiento','color' => '#ec4899', 'icon' => 'tv',          'children' => ['Streaming', 'Cine/Teatro', 'Eventos', 'Videojuegos']],
            ['name' => 'Ropa',           'color' => '#f59e0b', 'icon' => 'shirt',       'children' => []],
            ['name' => 'Tecnología',     'color' => '#6366f1', 'icon' => 'laptop',      'children' => ['Software', 'Hardware', 'Accesorios']],
            ['name' => 'Finanzas',       'color' => '#10b981', 'icon' => 'landmark',    'children' => ['Comisiones bancarias', 'Seguros', 'Intereses TDC']],
            ['name' => 'Otros gastos',   'color' => '#6b7280', 'icon' => 'ellipsis',    'children' => []],
        ];

        $incomes = [
            ['name' => 'Honorarios',     'color' => '#10b981', 'icon' => 'briefcase',   'children' => []],
            ['name' => 'Docencia',       'color' => '#06b6d4', 'icon' => 'school',      'children' => []],
            ['name' => 'Capacitaciones', 'color' => '#8b5cf6', 'icon' => 'presentation','children' => []],
            ['name' => 'Otros ingresos', 'color' => '#6b7280', 'icon' => 'ellipsis',    'children' => []],
        ];

        foreach ($expenses as $data) {
            $parent = Category::create([
                'name'  => $data['name'],
                'kind'  => Category::KIND_EXPENSE,
                'color' => $data['color'],
                'icon'  => $data['icon'],
            ]);
            foreach ($data['children'] as $child) {
                Category::create([
                    'parent_id' => $parent->id,
                    'name'      => $child,
                    'kind'      => Category::KIND_EXPENSE,
                    'color'     => $data['color'],
                    'icon'      => $data['icon'],
                ]);
            }
        }

        foreach ($incomes as $data) {
            Category::create([
                'name'  => $data['name'],
                'kind'  => Category::KIND_INCOME,
                'color' => $data['color'],
                'icon'  => $data['icon'],
            ]);
        }
    }
}
