<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateManagedUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $states = $this->input('state_ids', []);
        $countrywide = in_array('countrywide', is_array($states) ? $states : [], true);
        $this->merge([
            'countrywide_access' => $countrywide,
            'state_ids' => array_values(array_filter(is_array($states) ? $states : [], fn ($id) => $id !== 'countrywide')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->isAdministrator() ?? false;
    }

    public function rules(): array
    {
        /** @var User $managedUser */
        $managedUser = $this->route('user');

        return [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($managedUser)],
            'role' => ['required', Rule::in(array_keys(User::roleLabels()))],
            'is_active' => ['required', 'boolean'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'countrywide_access' => ['required', 'boolean'],
            'state_ids' => ['nullable', 'array'],
            'state_ids.*' => ['integer', 'distinct', 'exists:states,id'],
            'municipality_ids' => ['nullable', 'array'],
            'municipality_ids.*' => ['integer', 'distinct', 'exists:municipalities,id'],
        ];
    }
}
