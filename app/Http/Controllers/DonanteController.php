<?php

namespace App\Http\Controllers;

use App\Models\Donante;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DonanteController extends Controller
{
    public function index(): View
    {
        return view('donantes.index', ['donantes' => Donante::withCount('proyectos')->orderBy('nombre')->paginate(20)]);
    }

    public function create(): View
    {
        return view('donantes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Donante::create($this->validated($request));
        return redirect()->route('donantes.index')->with('success', 'Donante creado correctamente.');
    }

    public function edit(Donante $donante): View
    {
        return view('donantes.edit', compact('donante'));
    }

    public function update(Request $request, Donante $donante): RedirectResponse
    {
        $donante->update($this->validated($request, $donante));
        return redirect()->route('donantes.index')->with('success', 'Donante actualizado correctamente.');
    }

    public function destroy(Donante $donante): RedirectResponse
    {
        if ($donante->proyectos()->exists()) {
            return back()->with('error', 'No puede eliminar el donante porque tiene proyectos asociados. Puede marcarlo como inactivo.');
        }
        $donante->delete();
        return redirect()->route('donantes.index')->with('success', 'Donante eliminado correctamente.');
    }

    private function validated(Request $request, ?Donante $donante = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('donantes')->ignore($donante)],
            'estatus' => ['required', 'boolean'],
            'enlaces' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
