<?php

namespace App\Http\Controllers;

use App\Models\Indicador;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IndicadorController extends Controller
{
    public function index(): View
    {
        return view('indicadores.index', [
            'indicadores' => Indicador::withCount('proyectos')->orderBy('codigo')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('indicadores.create', $this->formData());
    }

    public function store(Request $request): RedirectResponse
    {
        Indicador::create($this->validated($request));
        return redirect()->route('indicadores.index')->with('success', 'Indicador creado correctamente.');
    }

    public function edit(Indicador $indicador): View
    {
        return view('indicadores.edit', compact('indicador') + $this->formData());
    }

    public function update(Request $request, Indicador $indicador): RedirectResponse
    {
        $indicador->update($this->validated($request, $indicador));
        return redirect()->route('indicadores.index')->with('success', 'Indicador actualizado correctamente.');
    }

    public function destroy(Indicador $indicador): RedirectResponse
    {
        if ($indicador->proyectos()->exists()) {
            return back()->with('error', 'No puede eliminar el indicador porque está asignado a uno o más proyectos.');
        }
        $indicador->delete();
        return redirect()->route('indicadores.index')->with('success', 'Indicador eliminado correctamente.');
    }

    private function formData(): array
    {
        return [
            'espacios' => Indicador::ESPACIOS_COORDINACION,
            'poblaciones' => Indicador::POBLACIONES_DIRIGIDAS,
        ];
    }

    private function validated(Request $request, ?Indicador $indicador = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('indicadores')->ignore($indicador)],
            'descripcion' => ['required', 'string', 'max:255'],
            'unidad_conteo' => ['required', 'string', 'max:100'],
            'espacio_coordinacion' => ['required', Rule::in(Indicador::ESPACIOS_COORDINACION)],
            'poblacion_dirigida' => ['required', Rule::in(Indicador::POBLACIONES_DIRIGIDAS)],
        ]);
    }
}
