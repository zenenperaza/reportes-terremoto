<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ActividadController extends Controller
{
    public function index(): View
    {
        return view('actividades.index', ['actividades' => Actividad::withCount('asignacionesIndicadores')->orderBy('codigo')->paginate(20)]);
    }

    public function create(): View { return view('actividades.create'); }

    public function store(Request $request): RedirectResponse
    {
        Actividad::create($this->validated($request));
        return redirect()->route('actividades.index')->with('success', 'Actividad creada correctamente.');
    }

    public function edit(Actividad $actividad): View { return view('actividades.edit', compact('actividad')); }

    public function update(Request $request, Actividad $actividad): RedirectResponse
    {
        $actividad->update($this->validated($request, $actividad));
        return redirect()->route('actividades.index')->with('success', 'Actividad actualizada correctamente.');
    }

    public function destroy(Actividad $actividad): RedirectResponse
    {
        if ($actividad->asignacionesIndicadores()->exists()) {
            return back()->with('error', 'No puede eliminar la actividad porque está asignada a uno o más indicadores.');
        }
        $actividad->delete();
        return redirect()->route('actividades.index')->with('success', 'Actividad eliminada correctamente.');
    }

    private function validated(Request $request, ?Actividad $actividad = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('actividades')->ignore($actividad)],
            'descripcion' => ['required', 'string', 'max:255'],
        ]);
    }
}
