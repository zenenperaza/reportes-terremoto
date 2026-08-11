<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\ActividadIndicador;
use App\Models\IndicadorProyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class IndicadorProyectoActividadController extends Controller
{
    public function index(IndicadorProyecto $indicadorProyecto): View
    {
        $indicadorProyecto->load(['proyecto.donante', 'indicador']);
        return view('proyectos.indicadores.actividades.index', [
            'indicadorProyecto' => $indicadorProyecto,
            'asignaciones' => $indicadorProyecto->asignacionesActividades()->with('actividad')->orderBy('id')->paginate(20),
            'actividadesDisponibles' => Actividad::whereNotIn('id', $indicadorProyecto->asignacionesActividades()->select('actividad_id'))->orderBy('codigo')->get(),
        ]);
    }

    public function store(Request $request, IndicadorProyecto $indicadorProyecto): RedirectResponse
    {
        $data = $request->validate([
            'actividad_id' => ['required', 'integer', 'exists:actividades,id'],
            'meta' => ['nullable', 'integer', 'min:0'],
        ]);
        if ($indicadorProyecto->asignacionesActividades()->where('actividad_id', $data['actividad_id'])->exists()) {
            throw ValidationException::withMessages(['actividad_id' => 'La actividad ya está asignada a este indicador del proyecto.']);
        }
        $indicadorProyecto->asignacionesActividades()->create($data + ['estatus' => true]);
        return back()->with('success', 'Actividad agregada al indicador.');
    }

    public function update(Request $request, ActividadIndicador $actividadIndicador): RedirectResponse
    {
        $actividadIndicador->update($request->validate([
            'estatus' => ['required', 'boolean'],
            'meta' => ['nullable', 'integer', 'min:0'],
        ]));
        return back()->with('success', 'Actividad del indicador actualizada.');
    }

    public function destroy(ActividadIndicador $actividadIndicador): RedirectResponse
    {
        $actividadIndicador->delete();
        return back()->with('success', 'Actividad desvinculada del indicador.');
    }

}
