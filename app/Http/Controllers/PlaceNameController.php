<?php

namespace App\Http\Controllers;

use App\Exceptions\ReverseGeocodingException;
use App\Models\Municipality;
use App\Models\PlaceName;
use App\Models\State;
use App\Services\ReverseGeocoder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlaceNameController extends Controller
{
    public function index(): View
    {
        return view('place-names.index', [
            'placeNames' => PlaceName::query()
                ->with(['creator:id,name', 'state:id,name', 'municipality:id,name', 'parish:id,name'])
                ->orderBy('name')->paginate(25),
            'states' => State::query()->orderBy('name')->get(['id', 'name']),
            'installationTypes' => config('reports.installation_types'),
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

    public function edit(PlaceName $placeName): View
    {
        $placeName->load(['state:id,name', 'municipality:id,name', 'parish:id,name']);

        return view('place-names.edit', [
            'placeName' => $placeName,
            'states' => State::query()->orderBy('name')->get(['id', 'name']),
            'municipalities' => $placeName->state
                ? $placeName->state->municipalities()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'parishes' => $placeName->municipality
                ? $placeName->municipality->parishes()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'installationTypes' => config('reports.installation_types'),
        ]);
    }

    public function destroy(PlaceName $placeName): RedirectResponse
    {
        $placeName->delete();

        return redirect()->route('place-names.index')->with('success', 'Nombre del lugar eliminado del catálogo. Los registros históricos no fueron modificados.');
    }

    /** @return array<string, mixed> */
    private function validatedName(Request $request, ?PlaceName $placeName = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $validator = Validator::make(
            $request->all(),
            [
                'name' => ['required', 'string', 'max:200', Rule::unique('place_names', 'name')->ignore($placeName)],
                'state_id' => ['required', 'integer', 'exists:states,id'],
                'municipality_id' => [
                    'required', 'integer',
                    Rule::exists('municipalities', 'id')->where('state_id', $request->input('state_id')),
                ],
                'parish_id' => [
                    'required', 'integer',
                    Rule::exists('parishes', 'id')->where('municipality_id', $request->input('municipality_id')),
                ],
                'installation_type' => ['required', Rule::in(config('reports.installation_types'))],
                'latitude' => ['required', 'numeric', 'between:0.5,12.7'],
                'longitude' => ['required', 'numeric', 'between:-74,-59'],
                'altitude' => ['nullable', 'numeric', 'between:-500,10000'],
                'gps_accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            ],
            [
                'name.required' => 'Ingrese el nombre específico del lugar.',
                'name.unique' => 'Este nombre del lugar ya existe.',
                'state_id.required' => 'Seleccione el estado.',
                'municipality_id.required' => 'Seleccione el municipio.',
                'parish_id.required' => 'Seleccione la parroquia.',
                'installation_type.required' => 'Seleccione el tipo de instalación.',
                'latitude.required' => 'Ingrese la latitud.',
                'longitude.required' => 'Ingrese la longitud.',
            ],
        );

        $validator->after(function ($validator) use ($request): void {
            $this->validateCoordinatesForLocation($validator, $request);
        });

        return $validator->validate();
    }

    private function validateCoordinatesForLocation($validator, Request $request): void
    {
        if (
            $validator->errors()->has('latitude')
            || $validator->errors()->has('longitude')
            || $validator->errors()->has('state_id')
            || $validator->errors()->has('municipality_id')
        ) {
            return;
        }

        $state = State::find((int) $request->input('state_id'));
        $municipality = Municipality::find((int) $request->input('municipality_id'));

        if (! $state || ! $municipality) {
            return;
        }

        try {
            $reverseGeocoder = app(ReverseGeocoder::class);
            $address = $reverseGeocoder->resolve((float) $request->input('latitude'), (float) $request->input('longitude'));
        } catch (ReverseGeocodingException $exception) {
            $validator->errors()->add('latitude', $exception->getMessage());

            return;
        }

        if (! $reverseGeocoder->isInVenezuela($address)) {
            $validator->errors()->add('latitude', 'Las coordenadas deben corresponder al territorio venezolano.');

            return;
        }

        if ($reverseGeocoder->matchesAdministrativeLocation($address, $state->name, $municipality->name) === false) {
            $validator->errors()->add('latitude', 'Las coordenadas no coinciden con el Estado y Municipio seleccionados.');
        }
    }
}
