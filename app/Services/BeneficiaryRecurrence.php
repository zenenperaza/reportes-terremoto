<?php

namespace App\Services;

use App\Models\Beneficiary;
use Illuminate\Support\Facades\DB;

class BeneficiaryRecurrence
{
    /** Check the whole authorized project, not an indicator, location, author or period. */
    public function check(int $projectId, array $person): array
    {
        $id = strtoupper(preg_replace('/[\s.\-]+/', '', (string) ($person['national_id'] ?? '')));
        $name = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($person['full_name'] ?? ''))), 'UTF-8');
        $byName = $name !== '' && isset($person['age']) && $person['age'] !== '' && filled($person['sex'] ?? null);
        if ($id === '' && ! $byName) {
            return ['comparable' => false, 'is_recurrent' => false, 'matched_by' => null];
        }

        $matches = Beneficiary::query()->whereHas('report', fn ($q) => $q->where('proyecto_id', $projectId));
        if ($id !== '') {
            $digits = preg_replace('/^[VE]/', '', $id);
            $candidates = ctype_digit($id) ? [$id, 'V'.$id, 'E'.$id] : [$id, $digits];
            $column = DB::raw("REPLACE(REPLACE(REPLACE(UPPER(TRIM(national_id)), ' ', ''), '-', ''), '.', '')");
            $matches->whereIn($column, $candidates);
        } else {
            // Normalize repeated spaces without treating different spellings as the same identity.
            $column = 'TRIM(full_name)';
            for ($i = 0; $i < 5; $i++) $column = "REPLACE($column, '  ', ' ')";
            $matches->whereRaw("UPPER($column) = ?", [$name])->where('age', $person['age'])->where('sex', $person['sex']);
        }

        // Only expose existence; never return another registrant's profile or number of attendances.
        return ['comparable' => true, 'is_recurrent' => $matches->exists(),
            'matched_by' => $id !== '' ? 'cédula y proyecto' : 'nombre, edad, sexo y proyecto'];
    }
}
