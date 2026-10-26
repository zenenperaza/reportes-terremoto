<?php

namespace App\Http\Requests;

use App\Models\BuzonEntry;
use App\Models\Proyecto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBuzonEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('entry');
        return $this->user()?->can('Buzon') && (! $entry || BuzonEntry::visibleTo($this->user())->whereKey($entry->id)->exists());
    }

    public function rules(): array
    {
        $current = $this->route('entry');
        $projects = Proyecto::query()->where(fn ($query) => $query->where('estatus', true)
            ->when($current, fn ($query) => $query->orWhere('proyectos.id', $current->proyecto_id)))
            ->when(! $this->user()->isAdministrator(), fn ($query) => $query->whereHas('users', fn ($query) => $query->whereKey($this->user()->id)));

        return [
            'proyecto_id' => ['required', 'integer', Rule::in($projects->pluck('id')->all())],
            'opinion_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'place_name' => ['required', 'string', 'max:200'],
            'full_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'age' => ['nullable', 'integer', 'between:0,120'],
            'gender' => ['nullable', Rule::in(array_keys(BuzonEntry::GENDERS))],
            'disabilities' => ['nullable', 'array', 'max:4'],
            'disabilities.*' => ['required', 'distinct', Rule::in(array_keys(BuzonEntry::DISABILITIES))],
            'afro_descendant' => ['nullable', 'boolean'],
            'opinion' => ['required', Rule::in(array_keys(BuzonEntry::OPINIONS))],
            'feedback_type' => ['required', Rule::in(array_keys(BuzonEntry::FEEDBACK_TYPES))],
            'comment' => ['nullable', 'string', 'max:10000'],
            'improvement' => ['nullable', 'string', 'max:10000'],
            'version' => [$current ? 'required' : 'prohibited', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['place_name', 'full_name', 'phone', 'comment', 'improvement'] as $field) {
            if (is_string($this->input($field))) {
                $value = trim($this->input($field));
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $selected = $this->input('disabilities', []) ?? [];
            if (is_array($selected) && in_array('none', $selected, true) && count($selected) > 1) {
                $validator->errors()->add('disabilities', 'No combine Ninguna con otro tipo de discapacidad.');
            }
        });
    }

    public function messages(): array
    {
        return ['proyecto_id.in' => 'Seleccione un proyecto activo asignado a su usuario.',
            'required' => 'El campo :attribute es obligatorio.',
            'in' => 'Seleccione una opción válida en :attribute.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'age.between' => 'La edad debe estar entre 0 y 120 años.',
            'boolean' => 'Seleccione Sí, No o deje el campo sin indicar.',
            'array' => 'La selección de :attribute no es válida.',
            'distinct' => 'No repita opciones en :attribute.',
            'max.string' => 'El campo :attribute no puede superar :max caracteres.',
            'opinion_date.before_or_equal' => 'La fecha de la opinión no puede ser futura.',
            'opinion_date.date_format' => 'Ingrese una fecha de opinión válida.'];
    }

    public function attributes(): array
    {
        return ['proyecto_id' => 'proyecto', 'place_name' => 'lugar', 'opinion_date' => 'fecha de la opinión',
            'opinion' => 'opinión', 'feedback_type' => 'tipo de retroalimentación', 'version' => 'versión'];
    }
}
