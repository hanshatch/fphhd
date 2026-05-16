<?php

namespace App\Http\Controllers;

use App\Models\Source;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SourceController extends Controller
{
    public function index(): View
    {
        $sources = Source::orderBy('kind')->orderBy('name')->get();

        return view('pages.sources.index', compact('sources'));
    }

    public function create(): View
    {
        return view('pages.sources.form', ['source' => new Source()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name'  => 'required|string|max:100',
            'kind'  => 'required|in:agency,university,training,other',
            'notes' => 'nullable|string|max:500',
        ]);

        Source::create($request->only('name', 'kind', 'notes'));

        return redirect()->route('sources.index')->with('status', 'Fuente creada.');
    }

    public function edit(Source $source): View
    {
        return view('pages.sources.form', compact('source'));
    }

    public function update(Request $request, Source $source): RedirectResponse
    {
        $request->validate([
            'name'  => 'required|string|max:100',
            'kind'  => 'required|in:agency,university,training,other',
            'notes' => 'nullable|string|max:500',
        ]);

        $source->update($request->only('name', 'kind', 'notes'));

        return redirect()->route('sources.index')->with('status', 'Fuente actualizada.');
    }

    public function destroy(Source $source): RedirectResponse
    {
        if ($source->transactions()->exists()) {
            $source->update(['is_archived' => true]);

            return redirect()->route('sources.index')->with('status', 'Fuente archivada (tiene movimientos asociados).');
        }

        $source->delete();

        return redirect()->route('sources.index')->with('status', 'Fuente eliminada.');
    }
}
