<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBeneficiaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $beneficiaryOptions = config('reports.beneficiary_options');

        return [
            'full_name' => ['nullable', 'string', 'max:150'],
            'age' => ['required', 'integer', 'min:0', 'max:120'],
            'sex' => ['required', Rule::in($beneficiaryOptions['sexes'])],
            'national_id' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:30'],
            'disability' => ['nullable', Rule::in($beneficiaryOptions['disabilities'])],
            'ethnicity' => ['nullable', Rule::in($beneficiaryOptions['ethnicities'])],
            'pregnant_lactating' => ['nullable', Rule::in($beneficiaryOptions['pregnant_lactating'])],
            'is_recurrent' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'boolean' => 'El campo :attribute debe contener una opción válida.',
            'in' => 'La opción seleccionada en :attribute no es válida.',
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'nombre y apellido',
            'age' => 'edad',
            'sex' => 'sexo',
            'national_id' => 'cédula',
            'phone' => 'teléfono',
            'disability' => 'discapacidad',
            'ethnicity' => 'indígena / etnia',
            'pregnant_lactating' => 'embarazada o lactante',
            'is_recurrent' => 'recurrente',
        ];
    }
}
