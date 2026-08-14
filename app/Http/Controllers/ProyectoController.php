<?php

namespace App\Http\Controllers;

use App\Models\Donante;
use App\Models\Municipality;
use App\Models\Proyecto;
use App\Models\State;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProyectoController extends Controller
{
    public function index(): View
    {
        return view('proyectos.index', [
            'proyectos' => Proyecto::with(['donante', 'estados', 'municipios'])->withCount('indicadores')->orderByDesc('created_at')->paginate(20),
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
            'estados',
            'municipios.state',
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
        $data = $this->validated($request);
        DB::transaction(function () use ($data): void {
            $proyecto = Proyecto::create(collect($data)->except(['state_ids', 'municipality_ids'])->all());
            $proyecto->estados()->sync($data['state_ids']);
            $proyecto->municipios()->sync($data['municipality_ids'] ?? []);
        });
        return redirect()->route('proyectos.index')->with('success', 'Proyecto creado correctamente.');
    }

    public function edit(Proyecto $proyecto): View
    {
        return view('proyectos.edit', compact('proyecto') + $this->formData());
    }

    public function update(Request $request, Proyecto $proyecto): RedirectResponse
    {
        $data = $this->validated($request, $proyecto);
        DB::transaction(function () use ($data, $proyecto): void {
            $proyecto->update(collect($data)->except(['state_ids', 'municipality_ids'])->all());
            $proyecto->estados()->sync($data['state_ids']);
            $proyecto->municipios()->sync($data['municipality_ids'] ?? []);
        });
        return redirect()->route('proyectos.index')->with('success', 'Proyecto actualizado correctamente.');
    }

    public function destroy(Proyecto $proyecto): RedirectResponse
    {
        $proyecto->delete();
        return redirect()->route('proyectos.index')->with('success', 'Proyecto eliminado correctamente.');
    }

    private function formData(): array
    {
        return [
            'donantes' => Donante::orderBy('nombre')->get(),
            'states' => State::with(['municipalities' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?Proyecto $proyecto = null): array
    {
        $data = $request->validate([
            'donante_id' => ['required', 'integer', 'exists:donantes,id'],
            'estatus' => ['required', 'boolean'],
            'codigo' => ['required', 'string', 'max:50', Rule::unique('proyectos')->ignore($proyecto)],
            'descripcion' => ['required', 'string', 'max:255'],
            'inicio' => ['nullable', 'date'],
            'fin' => ['nullable', 'date', 'after_or_equal:inicio'],
            'state_ids' => ['required', 'array', 'min:1'],
            'state_ids.*' => ['integer', 'distinct', 'exists:states,id'],
            'municipality_ids' => ['nullable', 'array'],
            'municipality_ids.*' => ['integer', 'distinct', 'exists:municipalities,id'],
        ]);

        $validMunicipalityIds = Municipality::whereIn('state_id', $data['state_ids'])->pluck('id');
        if (collect($data['municipality_ids'] ?? [])->map(fn ($id) => (int) $id)->diff($validMunicipalityIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'municipality_ids' => 'Uno de los municipios no pertenece a los estados seleccionados.',
            ]);
        }

        return $data;
    }
}
