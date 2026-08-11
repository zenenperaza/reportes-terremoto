<?php

namespace App\Http\Controllers;

use App\Models\Servicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServicioController extends Controller
{
    public function index(): View
    {
        return view('servicios.index', ['servicios' => Servicio::withCount('asignacionesActividades')->orderBy('nombre')->paginate(20)]);
    }

    public function create(): View { return view('servicios.create'); }

    public function store(Request $request): RedirectResponse
    {
        Servicio::create($this->validated($request));
        return redirect()->route('servicios.index')->with('success', 'Servicio creado correctamente.');
    }

    public function edit(Servicio $servicio): View { return view('servicios.edit', compact('servicio')); }

    public function update(Request $request, Servicio $servicio): RedirectResponse
    {
        $servicio->update($this->validated($request, $servicio));
        return redirect()->route('servicios.index')->with('success', 'Servicio actualizado correctamente.');
    }

    public function destroy(Servicio $servicio): RedirectResponse
    {
        if ($servicio->asignacionesActividades()->exists()) {
            return back()->with('error', 'No puede eliminar el servicio porque está asignado a una o más actividades.');
        }
        $servicio->delete();
        return redirect()->route('servicios.index')->with('success', 'Servicio eliminado correctamente.');
    }

    private function validated(Request $request, ?Servicio $servicio = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255', Rule::unique('servicios')->ignore($servicio)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
