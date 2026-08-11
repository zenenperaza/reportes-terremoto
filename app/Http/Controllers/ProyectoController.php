<?php

namespace App\Http\Controllers;

use App\Models\Donante;
use App\Models\Proyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProyectoController extends Controller
{
    public function index(): View
    {
        return view('proyectos.index', [
            'proyectos' => Proyecto::with('donante')->withCount('indicadores')->orderByDesc('created_at')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('proyectos.create', $this->formData());
    }

    public function show(Proyecto $proyecto): View
    {
        $proyecto->load([
            'donante',
            'asignacionesIndicadores' => fn ($query) => $query->with([
                'indicador',
                'asignacionesActividades' => fn ($activities) => $activities->with([
                    'actividad',
                    'asignacionesServicios.servicio',
                ])->orderBy('id'),
            ])->orderBy('id'),
        ]);

        return view('proyectos.show', compact('proyecto'));
    }

    public function store(Request $request): RedirectResponse
    {
        Proyecto::create($this->validated($request));
        return redirect()->route('proyectos.index')->with('success', 'Proyecto creado correctamente.');
    }

    public function edit(Proyecto $proyecto): View
    {
        return view('proyectos.edit', compact('proyecto') + $this->formData());
    }

    public function update(Request $request, Proyecto $proyecto): RedirectResponse
    {
        $proyecto->update($this->validated($request, $proyecto));
        return redirect()->route('proyectos.index')->with('success', 'Proyecto actualizado correctamente.');
    }

    public function destroy(Proyecto $proyecto): RedirectResponse
    {
        $proyecto->delete();
        return redirect()->route('proyectos.index')->with('success', 'Proyecto eliminado correctamente.');
    }

    private function formData(): array
    {
        return ['donantes' => Donante::orderBy('nombre')->get()];
    }

    private function validated(Request $request, ?Proyecto $proyecto = null): array
    {
        return $request->validate([
            'donante_id' => ['required', 'integer', 'exists:donantes,id'],
            'estatus' => ['required', 'boolean'],
            'codigo' => ['required', 'string', 'max:50', Rule::unique('proyectos')->ignore($proyecto)],
            'descripcion' => ['required', 'string', 'max:255'],
            'inicio' => ['nullable', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:inicio'],
        ]);
    }
}
