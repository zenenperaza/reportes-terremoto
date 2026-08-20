<?php

namespace App\Http\Controllers;

use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SectorController extends Controller
{
    public function index(): View
    {
        return view('sectores.index', [
            'sectores' => Sector::query()
                ->withCount(['proyectos', 'activities'])
                ->orderByRaw('COALESCE(codigo, name)')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('sectores.create');
    }

    public function store(Request $request): RedirectResponse
    {
        Sector::create($this->normalizedData($request));

        return redirect()->route('sectores.index')->with('success', 'Sector creado correctamente.');
    }

    public function edit(Sector $sector): View
    {
        return view('sectores.edit', compact('sector'));
    }

    public function update(Request $request, Sector $sector): RedirectResponse
    {
        $sector->update($this->normalizedData($request, $sector));

        return redirect()->route('sectores.index')->with('success', 'Sector actualizado correctamente.');
    }

    public function destroy(Sector $sector): RedirectResponse
    {
        if ($sector->asignacionesProyectos()->exists()) {
            return back()->with('error', 'No puede eliminar el sector porque está asignado a uno o más proyectos.');
        }

        if ($sector->activities()->exists() || $sector->reports()->exists()) {
            return back()->with('error', 'No puede eliminar el sector porque posee registros relacionados.');
        }

        $sector->delete();

        return redirect()->route('sectores.index')->with('success', 'Sector eliminado correctamente.');
    }

    public function toggleStatus(Sector $sector): RedirectResponse
    {
        $sector->update(['estatus' => ! $sector->estatus]);

        return redirect()->route('sectores.index')->with(
            'success',
            $sector->estatus ? 'Sector activado correctamente.' : 'Sector desactivado correctamente.',
        );
    }

    private function normalizedData(Request $request, ?Sector $sector = null): array
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:50', Rule::unique('sectors', 'codigo')->ignore($sector)],
            'descripcion' => ['required', 'string', 'max:255', Rule::unique('sectors', 'name')->ignore($sector)],
        ]);

        return [
            'codigo' => trim($data['codigo']),
            'descripcion' => trim($data['descripcion']),
            'name' => trim($data['descripcion']),
            'slug' => Str::slug($data['codigo']).'-'.substr(md5($data['codigo']), 0, 8),
            'sort_order' => $sector?->sort_order ?? ((int) Sector::max('sort_order') + 1),
        ];
    }
}
