<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithTotpSession;

class CategoryInheritsParentStyleTest extends TestCase
{
    use RefreshDatabase, WithTotpSession;

    private function parent(): Category
    {
        return Category::create([
            'name'  => 'Salud',
            'kind'  => 'expense',
            'color' => '#dc4444',
            'icon'  => 'heart-pulse',
        ]);
    }

    public function test_una_subcategoria_hereda_color_icono_y_tipo_del_padre(): void
    {
        $user   = User::factory()->create();
        $parent = $this->parent();

        $this->actingAsVerified($user)->post(route('categories.store'), [
            'name'      => 'Actividades deportivas',
            'kind'      => 'income',      // incoherente a propósito
            'parent_id' => $parent->id,
            'color'     => '#6366f1',     // morado, como el bug reportado
            'icon'      => 'tag',
        ])->assertRedirect(route('categories.index'));

        $child = Category::where('name', 'Actividades deportivas')->firstOrFail();

        $this->assertSame($parent->color, $child->color);
        $this->assertSame($parent->icon, $child->icon);
        $this->assertSame($parent->kind, $child->kind);
    }

    public function test_una_categoria_raiz_conserva_su_color_e_icono(): void
    {
        $user = User::factory()->create();

        $this->actingAsVerified($user)->post(route('categories.store'), [
            'name'      => 'Suscripciones',
            'kind'      => 'expense',
            'parent_id' => null,
            'color'     => '#6366f1',
            'icon'      => 'tv',
        ])->assertRedirect(route('categories.index'));

        $root = Category::where('name', 'Suscripciones')->firstOrFail();

        $this->assertSame('#6366f1', $root->color);
        $this->assertSame('tv', $root->icon);
    }

    public function test_al_editar_y_asignarle_padre_tambien_hereda(): void
    {
        $user   = User::factory()->create();
        $parent = $this->parent();

        $suelta = Category::create([
            'name'  => 'Estudios',
            'kind'  => 'expense',
            'color' => '#6366f1',
            'icon'  => 'tag',
        ]);

        $this->actingAsVerified($user)->patch(route('categories.update', $suelta), [
            'name'      => 'Estudios',
            'kind'      => 'expense',
            'parent_id' => $parent->id,
            'color'     => '#6366f1',
            'icon'      => 'tag',
        ])->assertRedirect(route('categories.index'));

        $suelta->refresh();

        $this->assertSame($parent->color, $suelta->color);
        $this->assertSame($parent->icon, $suelta->icon);
    }
}
