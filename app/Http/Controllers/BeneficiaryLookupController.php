<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BeneficiaryLookupController extends Controller
{
    public function recurrence(Request $request): JsonResponse
    {
        $data = $request->validate([
            'activity_id' => ['required', 'integer', 'exists:activities,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'parish_id' => ['nullable', 'integer', 'exists:parishes,id'],
            'exclude_beneficiary_id' => ['nullable', 'integer', 'exists:beneficiaries,id'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'full_name' => ['nullable', 'string', 'max:150'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'sex' => ['nullable', Rule::in(config('reports.beneficiary_options.sexes'))],
        ]);

        $nationalId = $this->normalizeNationalId($data['national_id'] ?? '');
        $canCompareByPersonalData = filled($data['full_name'] ?? null)
            && isset($data['age'], $data['sex'])
            && $data['sex'] !== ''
            && isset($data['state_id'], $data['municipality_id'], $data['parish_id']);

        if ($nationalId === '' && ! $canCompareByPersonalData) {
            return response()->json(['possible_match' => false, 'matches' => 0]);
        }

        $matches = Beneficiary::query()
            ->whereHas('report', function ($query) use ($data, $nationalId): void {
                $query->where('activity_id', $data['activity_id']);

                if ($nationalId === '') {
                    $query
                        ->where('state_id', $data['state_id'])
                        ->where('municipality_id', $data['municipality_id'])
                        ->where('parish_id', $data['parish_id']);
                }
            });

        if (! empty($data['exclude_beneficiary_id'])) {
            $matches->whereKeyNot($data['exclude_beneficiary_id']);
        }

        if ($nationalId !== '') {
            $matches->whereRaw("REPLACE(REPLACE(UPPER(national_id), ' ', ''), '-', '') = ?", [$nationalId]);
            $matchedBy = 'cédula y actividad';
        } else {
            $normalizedName = mb_strtolower(trim(preg_replace('/\s+/', ' ', $data['full_name'])));
            $matches
                ->whereRaw('LOWER(TRIM(full_name)) = ?', [$normalizedName])
                ->where('age', $data['age'])
                ->where('sex', $data['sex']);
            $matchedBy = 'nombre, edad, sexo, ubicación y actividad';
        }

        $count = $matches->count();

        return response()->json([
            'possible_match' => $count > 0,
            'matches' => $count,
            'matched_by' => $matchedBy,
        ]);
    }

    private function normalizeNationalId(string $nationalId): string
    {
        return strtoupper(preg_replace('/[\s-]+/', '', trim($nationalId)));
    }
}
