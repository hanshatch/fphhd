<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $income  = Category::active()->ofKind('income')->roots()->with('children')->orderBy('name')->get();
        $expense = Category::active()->ofKind('expense')->roots()->with('children')->orderBy('name')->get();

        return view('pages.categories.index', compact('income', 'expense'));
    }

    public function create(): View
    {
        $parents = Category::active()->roots()->orderBy('kind')->orderBy('name')->get();

        return view('pages.categories.form', ['category' => new Category(), 'parents' => $parents]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'kind'      => 'required|in:income,expense',
            'parent_id' => 'nullable|exists:categories,id',
            'color'     => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon'      => 'nullable|string|max:50',
        ]);

        Category::create($this->inheritFromParent($request->only('name', 'kind', 'parent_id', 'color', 'icon')));

        return redirect()->route('categories.index')->with('status', 'Categoría creada.');
    }

    public function edit(Category $category): View
    {
        $parents = Category::active()->roots()->where('id', '!=', $category->id)
            ->orderBy('kind')->orderBy('name')->get();

        return view('pages.categories.form', compact('category', 'parents'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $request->validate([
            'name'      => 'required|string|max:100',
            'kind'      => 'required|in:income,expense',
            'parent_id' => 'nullable|exists:categories,id',
            'color'     => 'required|regex:/^#[0-9A-Fa-f]{6}$/',
            'icon'      => 'nullable|string|max:50',
        ]);

        $category->update($this->inheritFromParent($request->only('name', 'kind', 'parent_id', 'color', 'icon')));

        return redirect()->route('categories.index')->with('status', 'Categoría actualizada.');
    }

    /**
     * Una subcategoría siempre toma color, icono y tipo de su padre: así la
     * lista se lee como un bloque coherente y no hay hijos de otro color.
     */
    private function inheritFromParent(array $data): array
    {
        if (empty($data['parent_id'])) {
            return $data;
        }

        $parent = Category::find($data['parent_id']);

        if (! $parent) {
            return $data;
        }

        $data['color'] = $parent->color;
        $data['icon']  = $parent->icon;
        $data['kind']  = $parent->kind;

        return $data;
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->transactions()->exists()) {
            $category->update(['is_archived' => true]);

            return redirect()->route('categories.index')->with('status', 'Categoría archivada (tiene movimientos asociados).');
        }

        $category->delete();

        return redirect()->route('categories.index')->with('status', 'Categoría eliminada.');
    }
}
