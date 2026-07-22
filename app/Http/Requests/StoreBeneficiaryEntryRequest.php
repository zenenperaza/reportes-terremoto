<?php

namespace App\Http\Requests;

use App\Exceptions\ReverseGeocodingException;
use App\Models\Activity;
use App\Models\Municipality;
use App\Models\Parish;
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
            'place_name' => ['required', 'string', 'max:200', Rule::exists('place_names', 'name')],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            'altitude' => ['nullable', 'numeric', 'between:-500,10000'],
            'gps_accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],

            'sector_id' => ['required', 'integer', 'exists:sectors,id'],
            'activity_id' => ['required', 'integer', 'exists:activities,id'],
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
            'beneficiary.full_name' => 'nombre y apellido del beneficiario',
            'beneficiary.age' => 'edad del beneficiario',
            'beneficiary.sex' => 'sexo del beneficiario',
            'beneficiary.is_recurrent' => 'condición recurrente',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->validateCoordinatesAreInVenezuela($validator);

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
