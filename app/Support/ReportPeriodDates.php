<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ReportPeriodDates
{
    /** Actual dates of the authorized beneficiaries, scoped by period and reporting status. */
    public static function forBeneficiaries(Builder $beneficiaries): array
    {
        $bounds = (clone $beneficiaries)->reorder()
            ->join('reports', 'beneficiaries.report_id', '=', 'reports.id')
            ->toBase()->selectRaw('MIN(reports.report_date) as attention_min, MAX(reports.report_date) as attention_max, MIN(beneficiaries.created_at) as registered_min, MAX(beneficiaries.created_at) as registered_max')
            ->first();

        $result = [];
        foreach (['attention', 'registered'] as $group) {
            foreach (['min', 'max'] as $limit) {
                $value = $bounds->{$group.'_'.$limit};
                $result[$group][$limit] = $value === null ? null : substr((string) $value, 0, 10);
            }
        }

        return $result;
    }

    public static function validate(array $filters, array $bounds, array $fields): void
    {
        $errors = [];
        foreach ($fields as $field => $group) {
            $value = $filters[$field] ?? null;
            if (! filled($value)) {
                continue;
            }
            $min = $bounds[$group]['min'];
            $max = $bounds[$group]['max'];
            if ($min === null || $max === null) {
                $errors[$field] = 'No hay fechas registradas disponibles para este período y estado de reporte.';
            } elseif ($value < $min || $value > $max) {
                $errors[$field] = "Seleccione una fecha entre {$min} y {$max}, según los registros disponibles.";
            }
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
