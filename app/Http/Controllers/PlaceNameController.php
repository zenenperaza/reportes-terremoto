<?php

namespace App\Http\Controllers;

use App\Models\PlaceName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlaceNameController extends Controller
{
    public function index(): View
    {
        return view('place-names.index', [
            'placeNames' => PlaceName::query()->with('creator:id,name')->orderBy('name')->paginate(25),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedName($request);
        $data['created_by'] = $request->user()->id;
        PlaceName::create($data);

        return redirect()->route('place-names.index')->with('success', 'Nombre del lugar creado correctamente.');
    }

    public function update(Request $request, PlaceName $placeName): RedirectResponse
    {
        $placeName->update($this->validatedName($request, $placeName));

        return redirect()->route('place-names.index')->with('success', 'Nombre del lugar actualizado correctamente.');
    }

    public function destroy(PlaceName $placeName): RedirectResponse
    {
        $placeName->delete();

        return redirect()->route('place-names.index')->with('success', 'Nombre del lugar eliminado del catálogo. Los registros históricos no fueron modificados.');
    }

    /** @return array{name: string} */
    private function validatedName(Request $request, ?PlaceName $placeName = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate(
            ['name' => ['required', 'string', 'max:200', Rule::unique('place_names', 'name')->ignore($placeName)]],
            ['name.required' => 'Ingrese el nombre específico del lugar.', 'name.unique' => 'Este nombre del lugar ya existe.'],
        );
    }
}
