<?php

namespace App\Http\Controllers;

use App\Models\ActividadIndicador;
use App\Models\Servicio;
use App\Models\ServicioActividad;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ActividadIndicadorServicioController extends Controller
{
    public function index(ActividadIndicador $actividadIndicador): View
    {
        $actividadIndicador->load(['actividad', 'indicadorProyecto.proyecto.donante', 'indicadorProyecto.indicador']);
        return view('proyectos.indicadores.actividades.servicios.index', [
            'actividadIndicador' => $actividadIndicador,
            'asignaciones' => $actividadIndicador->asignacionesServicios()->with('servicio')->orderBy('id')->paginate(20),
            'serviciosDisponibles' => Servicio::whereNotIn('id', $actividadIndicador->asignacionesServicios()->select('servicio_id'))->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request, ActividadIndicador $actividadIndicador): RedirectResponse
    {
        $data = $request->validate([
            'servicio_id' => ['required', 'integer', 'exists:servicios,id'],
            'cantidad_disponible' => ['nullable', 'integer', 'min:0'],
        ]);
        if ($actividadIndicador->asignacionesServicios()->where('servicio_id', $data['servicio_id'])->exists()) {
            throw ValidationException::withMessages(['servicio_id' => 'El servicio ya está asignado a esta actividad.']);
        }
        $actividadIndicador->asignacionesServicios()->create($data + ['estatus' => true]);
        return back()->with('success', 'Servicio agregado a la actividad.');
    }

    public function update(Request $request, ServicioActividad $servicioActividad): RedirectResponse
    {
        $servicioActividad->update($request->validate([
            'estatus' => ['required', 'boolean'],
            'cantidad_disponible' => ['nullable', 'integer', 'min:0'],
        ]));
        return back()->with('success', 'Servicio de la actividad actualizado.');
    }

    public function destroy(ServicioActividad $servicioActividad): RedirectResponse
    {
        $servicioActividad->delete();
        return back()->with('success', 'Servicio desvinculado de la actividad.');
    }
}
