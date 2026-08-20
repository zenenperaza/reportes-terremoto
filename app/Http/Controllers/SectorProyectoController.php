<?php

namespace App\Http\Controllers;

use App\Models\Proyecto;
use App\Models\Sector;
use App\Models\SectorProyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SectorProyectoController extends Controller
{
    public function index(Proyecto $proyecto): View
    {
        $proyecto->load('donante');

        $asignaciones = $proyecto->asignacionesSectores()
            ->with('sector')
            ->withCount('asignacionesIndicadores')
            ->orderBy('id')
            ->paginate(20);

        $sectoresDisponibles = Sector::query()
            ->where('estatus', true)
            ->whereNotIn('id', $proyecto->asignacionesSectores()->select('sector_id'))
            ->orderByRaw('COALESCE(codigo, name)')
            ->get();

        return view('proyectos.sectores.index', compact('proyecto', 'asignaciones', 'sectoresDisponibles'));
    }

    public function store(Request $request, Proyecto $proyecto): RedirectResponse
    {
        $data = $request->validate([
            'sector_ids' => ['required', 'array', 'min:1'],
            'sector_ids.*' => ['required', 'integer', 'distinct', 'exists:sectors,id'],
        ], [
            'sector_ids.required' => 'Seleccione al menos un sector.',
            'sector_ids.min' => 'Seleccione al menos un sector.',
        ]);

        $proyecto->sectores()->syncWithoutDetaching($data['sector_ids']);

        return redirect()->route('proyectos.sectores.index', $proyecto)
            ->with('success', count($data['sector_ids']) === 1 ? 'Sector agregado al proyecto.' : 'Sectores agregados al proyecto.');
    }

    public function destroy(SectorProyecto $sectorProyecto): RedirectResponse
    {
        $proyectoId = $sectorProyecto->proyecto_id;
        $sectorProyecto->delete();

        return redirect()->route('proyectos.sectores.index', $proyectoId)
            ->with('success', 'Sector desvinculado del proyecto.');
    }
}
