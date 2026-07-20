<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BeneficiaryReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'parish_id' => ['nullable', 'integer', 'exists:parishes,id'],
            'installation_type' => ['nullable', Rule::in(config('reports.installation_types'))],
            'place_name' => ['nullable', 'string', 'max:200'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'is_recurrent' => ['nullable', Rule::in(['0', '1', 0, 1])],
        ]);

        $reports = $this->applyReportFilters($this->visibleReports($request), $filters)
            ->when(array_key_exists('is_recurrent', $filters) && $filters['is_recurrent'] !== null, fn (Builder $query) => $query->whereHas('beneficiaries', fn (Builder $beneficiaries) => $beneficiaries->where('is_recurrent', (bool) $filters['is_recurrent'])));
        $beneficiaries = Beneficiary::query()
            ->whereHas('report', function (Builder $query) use ($request, $filters): void {
                if (! $request->user()->isCoordinator()) {
                    $query->where('user_id', $request->user()->id);
                }

                $this->applyReportFilters($query, $filters);
            })
            ->when(array_key_exists('is_recurrent', $filters) && $filters['is_recurrent'] !== null, fn (Builder $query) => $query->where('is_recurrent', (bool) $filters['is_recurrent']))
            ->get(['age', 'sex', 'disability', 'ethnicity', 'pregnant_lactating']);

        $selectedState = State::find($filters['state_id'] ?? null);
        $selectedMunicipality = Municipality::find($filters['municipality_id'] ?? null);
        $selectedSector = Sector::find($filters['sector_id'] ?? null);

        return view('beneficiaries.summary', [
            'filters' => $filters,
            'summary' => $this->summary($beneficiaries),
            'reportCount' => $reports->count(),
            'states' => State::orderBy('name')->get(['id', 'name']),
            'municipalities' => $selectedState ? $selectedState->municipalities()->orderBy('name')->get(['id', 'name']) : collect(),
            'parishes' => $selectedMunicipality ? $selectedMunicipality->parishes()->orderBy('name')->get(['id', 'name']) : collect(),
            'sectors' => Sector::orderBy('sort_order')->get(['id', 'name']),
            'activities' => $selectedSector ? $selectedSector->activities()->where('active', true)->orderBy('sort_order')->get(['id', 'title']) : collect(),
            'installationTypes' => config('reports.installation_types'),
            'places' => $this->visibleReports($request)->whereNotNull('place_name')->distinct()->orderBy('place_name')->pluck('place_name'),
            'isConsolidated' => $request->user()->isCoordinator(),
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function applyReportFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('report_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('report_date', '<=', $to))
            ->when($filters['state_id'] ?? null, fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
            ->when($filters['municipality_id'] ?? null, fn (Builder $query, int $municipalityId) => $query->where('municipality_id', $municipalityId))
            ->when($filters['parish_id'] ?? null, fn (Builder $query, int $parishId) => $query->where('parish_id', $parishId))
            ->when($filters['installation_type'] ?? null, fn (Builder $query, string $type) => $query->where('installation_type', $type))
            ->when($filters['place_name'] ?? null, fn (Builder $query, string $place) => $query->where('place_name', $place))
            ->when($filters['sector_id'] ?? null, fn (Builder $query, int $sectorId) => $query->where('sector_id', $sectorId))
            ->when($filters['activity_id'] ?? null, fn (Builder $query, int $activityId) => $query->where('activity_id', $activityId));
    }

    private function visibleReports(Request $request): Builder
    {
        $query = Report::query();

        if (! $request->user()->isCoordinator()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query;
    }

    /** @param \Illuminate\Support\Collection<int, Beneficiary> $beneficiaries */
    private function summary($beneficiaries): array
    {
        $count = function (string $sex, int $minimumAge, ?int $maximumAge) use ($beneficiaries): int {
            return $beneficiaries->filter(function (Beneficiary $beneficiary) use ($sex, $minimumAge, $maximumAge): bool {
                return $beneficiary->sex === $sex
                    && $beneficiary->age >= $minimumAge
                    && ($maximumAge === null || $beneficiary->age <= $maximumAge);
            })->count();
        };

        return [
            'girls_0_5' => $count('Mujer', 0, 5),
            'boys_0_5' => $count('Hombre', 0, 5),
            'girls_6_11' => $count('Mujer', 6, 11),
            'boys_6_11' => $count('Hombre', 6, 11),
            'girls_12_17' => $count('Mujer', 12, 17),
            'boys_12_17' => $count('Hombre', 12, 17),
            'women_18_59' => $count('Mujer', 18, 59),
            'men_18_59' => $count('Hombre', 18, 59),
            'women_60_plus' => $count('Mujer', 60, null),
            'men_60_plus' => $count('Hombre', 60, null),
            'total' => $beneficiaries->count(),
            'disability' => $beneficiaries->where('disability', '!=', 'Ninguna')->count(),
            'ethnicity' => $beneficiaries->where('ethnicity', '!=', 'Ninguna')->count(),
            'pregnancy' => $beneficiaries->where('pregnant_lactating', 'Sí')->count(),
        ];
    }
}
