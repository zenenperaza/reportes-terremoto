<?php

namespace App\Http\Controllers;

use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\Proyecto;
use App\Models\SectorProyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IndicadorProyectoController extends Controller
{
    public function indexBySector(SectorProyecto $sectorProyecto): View
    {
        $sectorProyecto->load(['proyecto.donante', 'sector']);
        $proyecto = $sectorProyecto->proyecto;

        return view('proyectos.indicadores.index', [
            'proyecto' => $proyecto,
            'sectorProyecto' => $sectorProyecto,
            'asignaciones' => $sectorProyecto->asignacionesIndicadores()
                ->with('indicador')
                ->withCount('asignacionesActividades')
                ->orderBy('id')
                ->paginate(20),
            'indicadoresDisponibles' => Indicador::whereNotIn(
                'id',
                $proyecto->asignacionesIndicadores()
                    ->whereNotNull('sector_proyecto_id')
                    ->select('indicador_id')
            )->orderBy('codigo')->get(),
        ]);
    }

    public function storeBySector(Request $request, SectorProyecto $sectorProyecto): RedirectResponse
    {
        $data = $this->validatedAssignment($request);
        $proyecto = $sectorProyecto->proyecto;

        $asignacionExistente = $proyecto->asignacionesIndicadores()
            ->where('indicador_id', $data['indicador_id'])
            ->first();

        if ($asignacionExistente?->sector_proyecto_id) {
            $code = Indicador::findOrFail($data['indicador_id'])->codigo;
            throw ValidationException::withMessages([
                'indicador_id' => "El indicador {$code} ya está asignado a un sector de este proyecto.",
            ]);
        }

        if ($asignacionExistente) {
            $asignacionExistente->update([
                'sector_proyecto_id' => $sectorProyecto->id,
                'estatus' => true,
                'meta_cuantitativa' => $data['meta_cuantitativa'] ?? $asignacionExistente->meta_cuantitativa,
                'meta_cualitativa' => $data['meta_cualitativa'] ?? $asignacionExistente->meta_cualitativa,
            ]);
        } else {
            $sectorProyecto->asignacionesIndicadores()->create([
                'proyecto_id' => $proyecto->id,
                'indicador_id' => $data['indicador_id'],
                'estatus' => true,
                'meta_cuantitativa' => $data['meta_cuantitativa'] ?? null,
                'meta_cualitativa' => $data['meta_cualitativa'] ?? null,
            ]);
        }

        return redirect()->route('sector-proyecto.indicadores.index', $sectorProyecto)
            ->with('success', 'Indicador agregado al sector del proyecto.');
    }

    public function index(Proyecto $proyecto): RedirectResponse
    {
        return redirect()->route('proyectos.sectores.index', $proyecto)
            ->with('warning', 'Seleccione un sector para gestionar sus indicadores.');
    }

    public function store(Request $request, Proyecto $proyecto): RedirectResponse
    {
        return redirect()->route('proyectos.sectores.index', $proyecto)
            ->with('error', 'Los indicadores deben asignarse desde un sector del proyecto.');
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

        return redirect()->route(
            $indicadorProyecto->sector_proyecto_id ? 'sector-proyecto.indicadores.index' : 'proyectos.indicadores.index',
            $indicadorProyecto->sector_proyecto_id ?: $indicadorProyecto->proyecto_id,
        )
            ->with('success', 'Indicador del proyecto actualizado correctamente.');
    }

    public function destroy(IndicadorProyecto $indicadorProyecto): RedirectResponse
    {
        $proyectoId = $indicadorProyecto->proyecto_id;
        $sectorProyectoId = $indicadorProyecto->sector_proyecto_id;
        $indicadorProyecto->delete();

        return redirect()->route(
            $sectorProyectoId ? 'sector-proyecto.indicadores.index' : 'proyectos.indicadores.index',
            $sectorProyectoId ?: $proyectoId,
        )
            ->with('success', 'Indicador desvinculado del proyecto.');
    }

    private function validatedAssignment(Request $request): array
    {
        return $request->validate([
            'indicador_id' => ['required', 'integer', 'exists:indicadores,id'],
            'meta_cuantitativa' => ['nullable', 'integer', 'min:0'],
            'meta_cualitativa' => ['nullable', 'string'],
        ], [
            'indicador_id.required' => 'Seleccione un indicador.',
        ]);
    }
}
