<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Proyecto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreManagedUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $states = $this->input('state_ids', []);
        $countrywide = in_array('countrywide', is_array($states) ? $states : [], true);
        $this->merge([
            'countrywide_access' => $countrywide,
            'can_mark_reported' => $this->boolean('can_mark_reported'),
            'state_ids' => array_values(array_filter(is_array($states) ? $states : [], fn ($id) => $id !== 'countrywide')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(array_keys(User::roleLabels()))],
            'is_active' => ['required', 'boolean'],
            'can_mark_reported' => ['required', 'boolean'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'countrywide_access' => ['required', 'boolean'],
            'state_ids' => ['nullable', 'array'],
            'state_ids.*' => ['integer', 'distinct', 'exists:states,id'],
            'municipality_ids' => ['nullable', 'array'],
            'municipality_ids.*' => ['integer', 'distinct', 'exists:municipalities,id'],
            'project_ids' => [Rule::requiredIf(fn () => Proyecto::exists()), 'array', 'min:1'],
            'project_ids.*' => ['integer', 'distinct', 'exists:proyectos,id'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('role') === 'admin') return;

            $projects = Proyecto::with(['estados.municipalities:id,state_id', 'municipios:id'])->whereKey($this->input('project_ids', []))->get();
            $allowedStates = $projects->flatMap->estados->pluck('id')->unique();
            $allowedMunicipalities = $projects->flatMap(fn ($project) => $project->municipios->isNotEmpty()
                ? $project->municipios
                : $project->estados->flatMap->municipalities
            )->pluck('id')->unique();

            if (collect($this->input('state_ids', []))->map(fn ($id) => (int) $id)->diff($allowedStates)->isNotEmpty()) {
                $validator->errors()->add('state_ids', 'Solo puede asignar estados pertenecientes a los proyectos seleccionados.');
            }
            if (collect($this->input('municipality_ids', []))->map(fn ($id) => (int) $id)->diff($allowedMunicipalities)->isNotEmpty()) {
                $validator->errors()->add('municipality_ids', 'Solo puede asignar municipios pertenecientes a los proyectos seleccionados.');
            }
        }];
    }
}
