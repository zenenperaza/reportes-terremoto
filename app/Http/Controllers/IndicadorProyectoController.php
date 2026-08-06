<?php

namespace App\Http\Controllers;

use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\Proyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IndicadorProyectoController extends Controller
{
    public function index(Proyecto $proyecto): View
    {
        $proyecto->load('donante');

        return view('proyectos.indicadores.index', [
            'proyecto' => $proyecto,
            'asignaciones' => $proyecto->asignacionesIndicadores()
                ->with('indicador')
                ->orderBy('id')
                ->paginate(20),
            'indicadoresDisponibles' => Indicador::whereNotIn(
                'id',
                $proyecto->asignacionesIndicadores()->select('indicador_id')
            )->orderBy('codigo')->get(),
        ]);
    }

    public function store(Request $request, Proyecto $proyecto): RedirectResponse
    {
        $data = $request->validate([
            'indicador_id' => ['required', 'integer', 'exists:indicadores,id'],
            'meta_cuantitativa' => ['nullable', 'integer', 'min:0'],
            'meta_cualitativa' => ['nullable', 'string'],
        ], [
            'indicador_id.required' => 'Seleccione un indicador.',
        ]);

        if ($proyecto->asignacionesIndicadores()->where('indicador_id', $data['indicador_id'])->exists()) {
            $code = Indicador::find($data['indicador_id'])->codigo;
            throw ValidationException::withMessages([
                'indicador_id' => "El indicador {$code} ya está asignado a este proyecto.",
            ]);
        }

        $proyecto->asignacionesIndicadores()->create([
            'indicador_id' => $data['indicador_id'],
            'estatus' => true,
            'meta_cuantitativa' => $data['meta_cuantitativa'] ?? null,
            'meta_cualitativa' => $data['meta_cualitativa'] ?? null,
        ]);

        return redirect()->route('proyectos.indicadores.index', $proyecto)
            ->with('success', 'Indicador agregado al proyecto.');
    }

    public function edit(IndicadorProyecto $indicadorProyecto): View
    {
        $indicadorProyecto->load(['proyecto.donante', 'indicador']);

        return view('proyectos.indicadores.edit', compact('indicadorProyecto'));
    }

    public function update(Request $request, IndicadorProyecto $indicadorProyecto): RedirectResponse
    {
        $data = $request->validate([
            'estatus' => ['required', 'boolean'],
            'meta_cuantitativa' => ['nullable', 'integer', 'min:0'],
            'meta_cualitativa' => ['nullable', 'string'],
        ]);

        $indicadorProyecto->update($data);

        return redirect()->route('proyectos.indicadores.index', $indicadorProyecto->proyecto_id)
            ->with('success', 'Indicador del proyecto actualizado correctamente.');
    }

    public function destroy(IndicadorProyecto $indicadorProyecto): RedirectResponse
    {
        $proyectoId = $indicadorProyecto->proyecto_id;
        $indicadorProyecto->delete();

        return redirect()->route('proyectos.indicadores.index', $proyectoId)
            ->with('success', 'Indicador desvinculado del proyecto.');
    }
}
