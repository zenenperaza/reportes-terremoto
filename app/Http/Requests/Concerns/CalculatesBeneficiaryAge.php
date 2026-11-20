<?php

namespace App\Http\Requests\Concerns;

use Carbon\CarbonImmutable;

trait CalculatesBeneficiaryAge
{
    private function normalizeBirthAge(?string $key = null, bool $batch = false): void
    {
        $reference = $this->route('beneficiary')?->report?->report_date
            ?? $this->route('report')?->report_date ?? $this->input('report_date');
        $normalize = static function (array $person) use ($reference): array {
            $person['age'] = null;
            $birth = $person['birth_date'] ?? null;
            if (is_string($birth) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $birth)
                && CarbonImmutable::canBeCreatedFromFormat($birth, '!Y-m-d') && $reference) {
                try {
                    $date = CarbonImmutable::createFromFormat('!Y-m-d', $birth);
                    $at = CarbonImmutable::parse($reference)->startOfDay();
                    if ($date->format('Y-m-d') === $birth && $date->lessThanOrEqualTo($at)) {
                        $person['age'] = (int) $date->diffInYears($at);
                    }
                } catch (\Throwable) {
                    // Invalid dates are rejected by the validation rules.
                }
            }
            return $person;
        };
        $data = $key === null ? $this->all() : $this->input($key);
        if (! is_array($data)) return;
        if ($batch) {
            foreach ($data as $index => $person) {
                if (is_array($person)) $data[$index] = $normalize($person);
            }
        } else {
            $data = $normalize($data);
        }
        $this->merge($key === null ? $data : [$key => $data]);
    }
}
