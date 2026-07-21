<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Beneficiary;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BeneficiaryReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $isRecurrent = $this->booleanFilter($filters, 'is_recurrent');
        $reported = $this->booleanFilter($filters, 'reported');

        $reports = $this->applyReportFilters($this->visibleReports($request), $filters);
        if ($isRecurrent !== null) {
            $reports->whereHas('beneficiaries', fn (Builder $beneficiaries) => $beneficiaries->where('is_recurrent', $isRecurrent));
        }
        if ($reported !== null) {
            $reports->whereHas('beneficiaries', fn (Builder $beneficiaries) => $reported ? $beneficiaries->whereNotNull('reported_at') : $beneficiaries->whereNull('reported_at'));
        }

        $beneficiaryQuery = $this->filteredBeneficiaries($request, $filters);
        $pendingBeneficiaryCount = (clone $beneficiaryQuery)->whereNull('reported_at')->count();
        $beneficiaries = (clone $beneficiaryQuery)->get(['age', 'sex', 'disability', 'ethnicity', 'pregnant_lactating']);
        $showReportedAt = $reported === true;
        $groupedBeneficiaries = $this->groupedBeneficiaries($beneficiaryQuery, $showReportedAt);

        $selectedState = State::find($filters['state_id'] ?? null);
        $selectedMunicipality = Municipality::find($filters['municipality_id'] ?? null);
        $selectedSector = Sector::find($filters['sector_id'] ?? null);

        return view('beneficiaries.summary', [
            'filters' => $filters,
            'summary' => $this->summary($beneficiaries),
            'reportCount' => $reports->count(),
            'pendingBeneficiaryCount' => $pendingBeneficiaryCount,
            'groupedBeneficiaries' => $groupedBeneficiaries,
            'showReportedAt' => $showReportedAt,
            'states' => State::orderBy('name')->get(['id', 'name']),
            'municipalities' => $selectedState ? $selectedState->municipalities()->orderBy('name')->get(['id', 'name']) : collect(),
            'parishes' => $selectedMunicipality ? $selectedMunicipality->parishes()->orderBy('name')->get(['id', 'name']) : collect(),
            'sectors' => Sector::orderBy('sort_order')->get(['id', 'name']),
            'activities' => $selectedSector
                ? $selectedSector->activities()->where('active', true)->orderBy('sort_order')->get(['id', 'title'])
                : Activity::query()->where('active', true)->orderBy('sector_id')->orderBy('sort_order')->get(['id', 'title']),
            'installationTypes' => config('reports.installation_types'),
            'places' => $this->visibleReports($request)->whereNotNull('place_name')->distinct()->orderBy('place_name')->pluck('place_name'),
            'isConsolidated' => $request->user()->isCoordinator(),
        ]);
    }

    public function markAsReported(Request $request): RedirectResponse
    {
        $filters = $this->validatedFilters($request);
        $reportedAt = $request->validate([
            'reported_at' => ['required', 'date', 'before_or_equal:today'],
        ])['reported_at'];
        $updated = $this->filteredBeneficiaries($request, $filters)
            ->whereNull('reported_at')
            ->update(['reported' => true, 'reported_at' => $reportedAt]);

        $query = array_filter($filters, static fn (mixed $value): bool => $value !== null && $value !== '');
        $message = $updated === 1
            ? '1 beneficiario fue actualizado como reportado con la fecha indicada.'
            : "{$updated} beneficiarios fueron actualizados como reportados con la fecha indicada.";

        return redirect()->route('beneficiaries.summary', $query)->with('success', $message);
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

    /** @return array<string, mixed> */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
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
            'reported' => ['nullable', Rule::in(['0', '1', 0, 1])],
        ]);
    }

    /** @param array<string, mixed> $filters */
    private function filteredBeneficiaries(Request $request, array $filters): Builder
    {
        $beneficiaries = Beneficiary::query()
            ->whereHas('report', function (Builder $query) use ($request, $filters): void {
                if (! $request->user()->isCoordinator()) {
                    $query->where('user_id', $request->user()->id);
                }

                $this->applyReportFilters($query, $filters);
            });

        $isRecurrent = $this->booleanFilter($filters, 'is_recurrent');
        if ($isRecurrent !== null) {
            $beneficiaries->where('is_recurrent', $isRecurrent);
        }

        $reported = $this->booleanFilter($filters, 'reported');
        if ($reported !== null) {
            $reported ? $beneficiaries->whereNotNull('reported_at') : $beneficiaries->whereNull('reported_at');
        }

        return $beneficiaries;
    }

    private function groupedBeneficiaries(Builder $beneficiaries, bool $includeReportedAt): \Illuminate\Support\Collection
    {
        $select = [
            'grouped_reports.report_date', 'states.name as state_name', 'municipalities.name as municipality_name',
            'parishes.name as parish_name', 'grouped_reports.place_name', 'activities.title as activity_title',
            DB::raw('COUNT(beneficiaries.id) as beneficiary_count'),
        ];
        $groupBy = [
            'grouped_reports.report_date', 'states.id', 'states.name', 'municipalities.id', 'municipalities.name',
            'parishes.id', 'parishes.name', 'grouped_reports.place_name', 'activities.id', 'activities.title',
        ];

        if ($includeReportedAt) {
            $select[] = 'beneficiaries.reported_at';
            $groupBy[] = 'beneficiaries.reported_at';
        }

        return (clone $beneficiaries)
            ->join('reports as grouped_reports', 'beneficiaries.report_id', '=', 'grouped_reports.id')
            ->join('states', 'grouped_reports.state_id', '=', 'states.id')
            ->join('municipalities', 'grouped_reports.municipality_id', '=', 'municipalities.id')
            ->join('parishes', 'grouped_reports.parish_id', '=', 'parishes.id')
            ->join('activities', 'grouped_reports.activity_id', '=', 'activities.id')
            ->select($select)
            ->groupBy($groupBy)
            ->orderByDesc('grouped_reports.report_date')
            ->orderBy('states.name')
            ->orderBy('municipalities.name')
            ->orderBy('parishes.name')
            ->orderBy('activities.title')
            ->toBase()
            ->get();
    }

    /** @param array<string, mixed> $filters */
    private function booleanFilter(array $filters, string $field): ?bool
    {
        if (! array_key_exists($field, $filters) || $filters[$field] === null || $filters[$field] === '') {
            return null;
        }

        return (bool) $filters[$field];
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
            'disability' => $beneficiaries->filter(fn (Beneficiary $beneficiary) => filled($beneficiary->disability) && $beneficiary->disability !== 'Ninguna')->count(),
            'ethnicity' => $beneficiaries->filter(fn (Beneficiary $beneficiary) => filled($beneficiary->ethnicity) && $beneficiary->ethnicity !== 'Ninguna')->count(),
            'pregnancy' => $beneficiaries->where('pregnant_lactating', 'Sí')->count(),
        ];
    }
}
