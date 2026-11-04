<?php

namespace App\Http\Requests\Concerns;

use App\Models\Beneficiary;
use Illuminate\Validation\Rule;

trait ValidatesBeneficiaryCaregiver
{
    private function caregiverRules(string $prefix = ''): array
    {
        return [
            $prefix.'caregiver_national_id' => ['nullable', 'string', 'max:30'],
            $prefix.'caregiver_full_name' => ['nullable', 'string', 'max:150'],
            $prefix.'caregiver_relationship' => ['nullable', 'string', 'max:100',
                Rule::in(Beneficiary::caregiverRelationshipOptions($this->route('beneficiary')?->caregiver_relationship))],
        ];
    }

    private function normalizeCaregiverPayload(?string $key = null, bool $batch = false): void
    {
        $normalize = static function (array $person): array {
            $age = $person['age'] ?? null;
            $adult = (is_int($age) || (is_string($age) && preg_match('/^\d+$/', $age))) && (int) $age >= 18;
            foreach (['caregiver_national_id', 'caregiver_full_name', 'caregiver_relationship'] as $field) {
                if ($adult) $person[$field] = null;
                elseif (isset($person[$field]) && is_string($person[$field])) $person[$field] = trim($person[$field]);
            }
            return $person;
        };
        $data = $key === null ? $this->all() : $this->input($key);
        if (! is_array($data)) return;
        if ($batch) {
            foreach ($data as $index => $person) {
                if (is_array($person)) $data[$index] = $normalize($person);
            }
            $this->merge([$key => $data]);
        } elseif ($key !== null) {
            $this->merge([$key => $normalize($data)]);
        } else {
            $this->merge(array_intersect_key($normalize($data), array_flip([
                'caregiver_national_id', 'caregiver_full_name', 'caregiver_relationship',
            ])));
        }
    }
}
