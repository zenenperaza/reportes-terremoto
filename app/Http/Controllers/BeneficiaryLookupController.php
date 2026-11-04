<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BeneficiaryLookupController extends Controller
{
    public function continuationLookup(Request $request, \App\Models\Report $report): JsonResponse
    {
        app(\App\Services\ReportContinuation::class)->assertAllowed($request->user(), $report);

        // Retain the existing personal-data visibility checks of the lookup.
        return $this->byNationalId($request);
    }

    public function continuationRecurrence(Request $request, \App\Models\Report $report, \App\Services\BeneficiaryRecurrence $recurrence): JsonResponse
    {
        app(\App\Services\ReportContinuation::class)->assertAllowed($request->user(), $report);
        // The record, not an arbitrary project sent by the browser, defines scope.
        $request->merge(['proyecto_id' => $report->proyecto_id]);

        return $this->projectRecurrence($request, $recurrence);
    }

    public function projectRecurrence(Request $request, \App\Services\BeneficiaryRecurrence $recurrence): JsonResponse
    {
        $data = $request->validate([
            'proyecto_id' => ['required', 'integer', 'exists:proyectos,id'],
            'national_id' => ['nullable', 'string', 'max:30'],
            'full_name' => ['nullable', 'string', 'max:150'],
            'age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'sex' => ['nullable', Rule::in(config('reports.beneficiary_options.sexes'))],
        ]);
        abort_unless($request->user()->isAdministrator() || $request->user()->projects()->whereKey($data['proyecto_id'])->exists(), 403);

        return response()->json($recurrence->check((int) $data['proyecto_id'], $data))
            ->header('Cache-Control', 'no-store, private');
    }

    public function byNationalId(Request $request): JsonResponse
    {
        $data = $request->validate(['national_id' => ['required', 'string', 'max:30']]);
        $nationalId = strtoupper(preg_replace('/[\s.\-]+/', '', trim($data['national_id'])));
        if (! preg_match('/^[VE]?\d{5,12}$/', $nationalId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'national_id' => 'Ingrese una cédula válida de al menos 5 dígitos; puede incluir V o E.',
            ]);
        }
        $digits = preg_replace('/^[VE]/', '', $nationalId);
        $candidates = ctype_digit($nationalId) ? [$digits, 'V'.$digits, 'E'.$digits] : [$nationalId, $digits];
        $normalizedColumn = DB::raw("REPLACE(REPLACE(REPLACE(UPPER(TRIM(national_id)), ' ', ''), '-', ''), '.', '')");
        $matches = Beneficiary::query()->whereIn($normalizedColumn, $candidates)
            ->whereHas('report', fn ($reports) => $request->user()->constrainVisibleReports($reports));
        $identities = (clone $matches)->get(['full_name', 'sex', 'national_id']);
        $names = $identities->pluck('full_name')->filter()->map(fn ($name) => Str::upper(Str::ascii(preg_replace('/\s+/', ' ', trim($name)))))->unique();
        $sexes = $identities->pluck('sex')->filter()->unique();
        $prefixes = $identities->pluck('national_id')->map(fn ($id) => strtoupper(preg_replace('/[\s.\-]+/', '', $id)))
            ->filter(fn ($id) => preg_match('/^[VE]/', $id))->map(fn ($id) => $id[0])->unique();
        if ($names->count() > 1 || $sexes->count() > 1 || $prefixes->count() > 1) {
            return response()->json(['matched' => false, 'ambiguous' => true])->header('Cache-Control', 'no-store, private');
        }
        $person = $matches->orderByDesc('created_at')->orderByDesc('id')->first([
            'full_name', 'age', 'sex', 'nationality', 'phone', 'disability', 'ethnicity', 'pregnant_lactating',
            'caregiver_national_id', 'caregiver_full_name', 'caregiver_relationship',
        ]);

        return response()->json(['matched' => $person !== null, 'ambiguous' => false, 'beneficiary' => $person])
            ->header('Cache-Control', 'no-store, private');
    }

    public function recurrence(Request $request): JsonResponse
    {
        $data = $request->validate([
            'activity_id' => ['nullable', 'required_without:indicador_proyecto_id', 'integer', 'exists:activities,id'],
            'indicador_proyecto_id' => ['nullable', 'required_without:activity_id', 'integer', 'exists:indicador_proyecto,id'],
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
                if (! empty($data['indicador_proyecto_id'])) {
                    $query->where('indicador_proyecto_id', $data['indicador_proyecto_id']);
                } else {
                    $query->where('activity_id', $data['activity_id']);
                }

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
            $normalizedName = mb_strtoupper(trim(preg_replace('/\s+/', ' ', $data['full_name'])), 'UTF-8');
            $matches
                ->whereRaw('TRIM(full_name) = ?', [$normalizedName])
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
