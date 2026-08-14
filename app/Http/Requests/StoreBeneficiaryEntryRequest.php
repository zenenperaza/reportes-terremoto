<?php

namespace App\Http\Requests;

use App\Exceptions\ReverseGeocodingException;
use App\Models\Activity;
use App\Models\ActividadIndicador;
use App\Models\IndicadorProyecto;
use App\Models\Proyecto;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\PlaceName;
use App\Models\State;
use App\Services\ReverseGeocoder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBeneficiaryEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $beneficiaryOptions = config('reports.beneficiary_options');
        $placeNameRules = ['required', 'string', 'max:200'];
        if (! $this->boolean('is_community_location')) {
            $placeNameRules[] = Rule::exists('place_names', 'name');
        }

        return [
            'report_id' => ['nullable', 'integer', 'exists:reports,id'],
            'report_date' => ['required', 'date', 'before_or_equal:today'],
            'reporter_first_name' => ['required', 'string', 'max:100'],
            'reporter_last_name' => ['required', 'string', 'max:100'],
            'reporter_email' => ['required', 'email', 'max:255'],
            'organization' => ['required', Rule::in(config('reports.organizations'))],
            'other_organization' => ['nullable', 'required_if:organization,Otro Socio Implementador', 'string', 'max:150'],

            'state_id' => ['required', 'integer', 'exists:states,id'],
            'municipality_id' => ['required', 'integer', 'exists:municipalities,id'],
            'parish_id' => ['required', 'integer', 'exists:parishes,id'],
            'installation_type' => ['required', Rule::in(config('reports.installation_types'))],
            'is_community_location' => ['nullable', 'boolean'],
            'place_name' => $placeNameRules,
            'latitude' => ['nullable', 'required_if:is_community_location,1', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_if:is_community_location,1', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'altitude' => ['nullable', 'numeric', 'between:-500,10000'],
            'gps_accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],

            'proyecto_id' => ['nullable', 'required_without:sector_id', 'integer', 'exists:proyectos,id'],
            'indicador_proyecto_id' => ['nullable', 'required_with:proyecto_id', 'integer', 'exists:indicador_proyecto,id'],
            'actividad_indicador_id' => ['nullable', 'integer', 'exists:actividad_indicador,id'],
            'servicio_actividad_ids' => ['nullable', 'array'],
            'servicio_actividad_ids.*' => ['integer', 'distinct', 'exists:servicio_actividad,id'],
            'sector_id' => ['nullable', 'required_without:proyecto_id', 'integer', 'exists:sectors,id'],
            'activity_id' => ['nullable', 'required_with:sector_id', 'integer', 'exists:activities,id'],
            'activity_details' => ['nullable', 'string', 'max:5000'],
            'qualitative_notes' => ['nullable', 'string', 'max:5000'],
            'evidence_1' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx', 'max:10240'],
            'evidence_2' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx', 'max:10240'],
            'evidence_3' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx', 'max:10240'],

            'beneficiary' => ['required', 'array'],
            'beneficiary.full_name' => ['nullable', 'string', 'max:150'],
            'beneficiary.age' => ['required', 'integer', 'min:0', 'max:120'],
            'beneficiary.sex' => ['required', Rule::in($beneficiaryOptions['sexes'])],
            'beneficiary.national_id' => ['nullable', 'string', 'max:30'],
            'beneficiary.phone' => ['nullable', 'string', 'max:30'],
            'beneficiary.disability' => ['nullable', Rule::in($beneficiaryOptions['disabilities'])],
            'beneficiary.ethnicity' => ['nullable', Rule::in($beneficiaryOptions['ethnicities'])],
            'beneficiary.pregnant_lactating' => ['nullable', Rule::in($beneficiaryOptions['pregnant_lactating'])],
            'beneficiary.is_recurrent' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->boolean('is_community_location')) {
            return;
        }

        $parish = Parish::find($this->input('parish_id'));
        $this->merge([
            'is_community_location' => true,
            'place_name' => $parish ? 'Comunidad '.$parish->name : null,
            'installation_type' => 'Comunidad / Espacio Comunitario',
            'latitude' => $parish?->latitude,
            'longitude' => $parish?->longitude,
            'altitude' => null,
            'gps_accuracy' => null,
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'required_if' => 'El campo :attribute es obligatorio.',
            'required_with' => 'El campo :attribute es obligatorio cuando se indica su coordenada relacionada.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'report_date' => 'fecha de registro',
            'reporter_first_name' => 'nombre de quien registra',
            'reporter_last_name' => 'apellido de quien registra',
            'reporter_email' => 'correo electrónico',
            'organization' => 'organización',
            'other_organization' => 'otra organización',
            'state_id' => 'estado',
            'municipality_id' => 'municipio',
            'parish_id' => 'parroquia',
            'installation_type' => 'tipo de instalación',
            'place_name' => 'nombre del lugar',
            'latitude' => 'latitud',
            'longitude' => 'longitud',
            'sector_id' => 'sector programático',
            'activity_id' => 'actividad a reportar',
            'proyecto_id' => 'proyecto',
            'indicador_proyecto_id' => 'actividad a reportar',
            'actividad_indicador_id' => 'actividad a reportar',
            'servicio_actividad_ids' => 'servicios',
            'beneficiary.full_name' => 'nombre y apellido del beneficiario',
            'beneficiary.age' => 'edad del beneficiario',
            'beneficiary.sex' => 'sexo del beneficiario',
            'beneficiary.is_recurrent' => 'condición recurrente',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->boolean('is_community_location')) {
                $this->validateCoordinatesAreInVenezuela($validator);
            }

            $municipality = Municipality::find($this->integer('municipality_id'));
            if ($municipality && $municipality->state_id !== $this->integer('state_id')) {
                $validator->errors()->add('municipality_id', 'El municipio no pertenece al estado seleccionado.');
            }

            $parish = Parish::find($this->integer('parish_id'));
            if ($parish && $parish->municipality_id !== $this->integer('municipality_id')) {
                $validator->errors()->add('parish_id', 'La parroquia no pertenece al municipio seleccionado.');
            }

            $activity = Activity::find($this->integer('activity_id'));
            if ($activity && $activity->sector_id !== $this->integer('sector_id')) {
                $validator->errors()->add('activity_id', 'La actividad no corresponde al sector seleccionado.');
            }

            $project = Proyecto::find($this->integer('proyecto_id'));
            if ($project && ! $this->user()->isAdministrator() && ! $this->user()->projects()->whereKey($project->id)->exists()) {
                $validator->errors()->add('proyecto_id', 'El proyecto no está asignado a su usuario.');
            }
            if ($project && ! $this->user()->canAccessLocation($this->integer('state_id'), $this->integer('municipality_id'))) {
                $validator->errors()->add('place_name', 'La ubicación seleccionada no está asignada a su usuario.');
            }
            if ($project && ! $project->coversLocation($this->integer('state_id'), $this->integer('municipality_id'))) {
                $validator->errors()->add('place_name', 'La ubicación seleccionada no pertenece al proyecto.');
            }
            $assignment = IndicadorProyecto::with('indicador')->find($this->integer('indicador_proyecto_id'));
            if ($assignment && ($assignment->proyecto_id !== $this->integer('proyecto_id') || ! $assignment->estatus)) {
                $validator->errors()->add('indicador_proyecto_id', 'El indicador no corresponde al proyecto seleccionado.');
            }
            if ($assignment?->indicador?->unidad_conteo === 'Personas') {
                $edadDesde = $assignment->indicador->edad_desde;
                $edadHasta = $assignment->indicador->edad_hasta;
                $edad = $this->input('beneficiary.age');
                if (is_numeric($edad) && ((int) $edad < $edadDesde || (int) $edad > $edadHasta)) {
                    $validator->errors()->add('beneficiary.age', "La edad debe estar entre $edadDesde y $edadHasta años para el indicador seleccionado.");
                }
            }
            $projectActivity = ActividadIndicador::find($this->integer('actividad_indicador_id'));
            if ($projectActivity && ($projectActivity->indicador_proyecto_id !== $this->integer('indicador_proyecto_id') || ! $projectActivity->estatus)) {
                $validator->errors()->add('actividad_indicador_id', 'La actividad no corresponde al indicador seleccionado.');
            }
            $availableServices = $projectActivity?->asignacionesServicios()->where('estatus', true)->pluck('id') ?? collect();
            $selectedServices = collect($this->input('servicio_actividad_ids', []))->map(fn ($id) => (int) $id);
            if ($selectedServices->diff($availableServices)->isNotEmpty()) {
                $validator->errors()->add('servicio_actividad_ids', 'Uno de los servicios no corresponde a la actividad seleccionada.');
            }

            $place = $this->boolean('is_community_location') ? null : PlaceName::where('name', $this->input('place_name'))->first();
            if ($place && $place->state_id && (
                $place->state_id !== $this->integer('state_id')
                || $place->municipality_id !== $this->integer('municipality_id')
                || $place->parish_id !== $this->integer('parish_id')
                || $place->installation_type !== $this->input('installation_type')
            )) {
                $validator->errors()->add('place_name', 'La ubicación del lugar seleccionado no coincide con los datos enviados.');
            }
        });
    }

    private function validateCoordinatesAreInVenezuela($validator): void
    {
        if ($validator->errors()->has('latitude') || $validator->errors()->has('longitude')) {
            return;
        }

        $latitude = $this->input('latitude');
        $longitude = $this->input('longitude');

        if ($latitude === null || $latitude === '' || $longitude === null || $longitude === '') {
            return;
        }

        try {
            $reverseGeocoder = app(ReverseGeocoder::class);
            $address = $reverseGeocoder->resolve((float) $latitude, (float) $longitude);
            $isInVenezuela = $reverseGeocoder->isInVenezuela($address);
        } catch (ReverseGeocodingException $exception) {
            $validator->errors()->add('latitude', $exception->getMessage());

            return;
        }

        if (! $isInVenezuela) {
            $validator->errors()->add('latitude', 'Las coordenadas deben corresponder al territorio venezolano.');

            return;
        }

        $state = State::find($this->integer('state_id'));
        $municipality = Municipality::find($this->integer('municipality_id'));
        if ($state && $municipality && $reverseGeocoder->matchesAdministrativeLocation($address, $state->name, $municipality->name) === false) {
            $validator->errors()->add('latitude', 'LAS COORDENADAS NO COINCIDEN CON EL ESTADO Y MUNICIPIO QUE DESEA REGISTRAR');
        }
    }
}
