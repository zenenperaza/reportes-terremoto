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

class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $beneficiaryOptions = config('reports.beneficiary_options');

        return [
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

            'beneficiaries' => ['required', 'array', 'min:1', 'max:1000'],
            'beneficiaries.*.full_name' => ['nullable', 'string', 'max:150'],
            'beneficiaries.*.age' => ['required', 'integer', 'min:0', 'max:120'],
            'beneficiaries.*.sex' => ['required', Rule::in($beneficiaryOptions['sexes'])],
            'beneficiaries.*.national_id' => ['nullable', 'string', 'max:30'],
            'beneficiaries.*.phone' => ['nullable', 'string', 'max:30'],
            'beneficiaries.*.disability' => ['required', Rule::in($beneficiaryOptions['disabilities'])],
            'beneficiaries.*.ethnicity' => ['required', Rule::in($beneficiaryOptions['ethnicities'])],
            'beneficiaries.*.pregnant_lactating' => ['required', Rule::in($beneficiaryOptions['pregnant_lactating'])],
            'beneficiaries.*.is_recurrent' => ['required', 'boolean'],

            'qualitative_notes' => ['nullable', 'string', 'max:5000'],
            'evidence_1' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx', 'max:10240'],
            'evidence_2' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx', 'max:10240'],
            'evidence_3' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,doc,docx,xlsx', 'max:10240'],
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

    public function attributes(): array
    {
        return [
            'report_date' => 'fecha del registro',
            'state_id' => 'estado',
            'municipality_id' => 'municipio',
            'parish_id' => 'parroquia',
            'activity_id' => 'actividad a reportar',
            'beneficiaries' => 'beneficiarios',
            'beneficiaries.*.full_name' => 'nombre y apellido del beneficiario',
            'beneficiaries.*.national_id' => 'cédula del beneficiario',
        ];
    }
}
