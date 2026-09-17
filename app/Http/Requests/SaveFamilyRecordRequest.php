<?php

namespace App\Http\Requests;

use App\Models\FamilyRecord;
use App\Support\CaseFormSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveFamilyRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->isMethod('POST') ? $this->user()->can('create', FamilyRecord::class) : $this->user()->can('update', $this->route('familyRecord'));
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('members') && $this->input('members') === null) {
            $this->merge(['members' => []]);
        }
    }

    public function rules(): array
    {
        return array_replace([
            'name' => ['required', 'string', 'max:200'], 'registered_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'restricted' => ['required', 'boolean', function ($attribute, $value, $fail): void {
                if (($value || $this->route('familyRecord')?->restricted) && ! $this->user()->can('gestionar casos vbg')) {
                    $fail('No tiene permiso para gestionar familias de acceso restringido.');
                }
            }],
            'version' => [$this->isMethod('POST') ? 'nullable' : 'required', 'integer', 'min:1'],
            'members' => ['nullable', 'array', 'max:50'],
        ] + CaseFormSchema::fieldsRules('details', config('case-forms.family_fields'))
            + CaseFormSchema::fieldsRules('members.*', config('case-forms.member_fields')), ['members.*.name' => ['required', 'string', 'max:250']]);
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->input('form_complete') !== '1') {
                $validator->errors()->add('members', 'El formulario llegó incompleto. Revise el límite max_input_vars del servidor.');
            }
        }];
    }
}
