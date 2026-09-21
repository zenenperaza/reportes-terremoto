<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\IndicadorProyecto;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Sector;
use App\Models\State;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GeneralReportController extends Controller
{
    private const AGE_GROUPS = [
        '0-5' => ['label' => 'Primera infancia (0 a 5 años)', 'from' => 0, 'to' => 5],
        '6-11' => ['label' => 'Niñez (6 a 11 años)', 'from' => 6, 'to' => 11],
        '12-17' => ['label' => 'Adolescencia (12 a 17 años)', 'from' => 12, 'to' => 17],
        '18-29' => ['label' => 'Jóvenes (18 a 29 años)', 'from' => 18, 'to' => 29],
        '30-59' => ['label' => 'Adultos (30 a 59 años)', 'from' => 30, 'to' => 59],
        '60+' => ['label' => 'Adultos mayores (60 años o más)', 'from' => 60, 'to' => 120],
    ];

    public function __invoke(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $beneficiaries = $this->filteredBeneficiaries($request, $filters)
            ->with([
                'report:id,report_date,created_at,state_id,municipality_id,parish_id,installation_type,place_name,sector_id,activity_id',
                'report.state:id,name',
            ])
            ->get(['id', 'report_id', 'age', 'sex', 'is_recurrent', 'reported_at', 'created_at']);

        $locations = $this->locationOptions($filters);
        $indicatorAssignments = IndicadorProyecto::query()
            ->with(['indicador:id,codigo,descripcion', 'asignacionSector:id,sector_id'])
            ->whereIn('id', $this->visibleReports($request)
                ->whereNotNull('indicador_proyecto_id')
                ->distinct()
                ->pluck('indicador_proyecto_id'))
            ->where('estatus', true)
            ->get(['id', 'indicador_id', 'sector_proyecto_id']);

        $indicators = $indicatorAssignments
            ->filter(fn (IndicadorProyecto $assignment): bool => $assignment->indicador !== null)
            ->groupBy('indicador_id')
            ->map(function ($assignments): array {
                $indicator = $assignments->first()->indicador;

                return [
                    'id' => $indicator->id,
                    'label' => trim($indicator->codigo.' - '.$indicator->descripcion, ' -'),
                    'sector_ids' => $assignments->pluck('asignacionSector.sector_id')->filter()->unique()->values()->all(),
                ];
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('general-reports.index', [
            'filters' => $filters,
            'ageGroups' => self::AGE_GROUPS,
            'states' => State::orderBy('name')->get(['id', 'name']),
            'municipalities' => $locations['municipalities'],
            'parishes' => $locations['parishes'],
            'sectors' => Sector::where('estatus', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'indicators' => $indicators,
            'installationTypes' => config('reports.installation_types'),
            'places' => $this->visibleReports($request)->whereNotNull('place_name')->where('place_name', '<>', '')->distinct()->orderBy('place_name')->pluck('place_name'),
            'summary' => $this->summary($beneficiaries),
            'charts' => $this->charts($beneficiaries),
        ]);
    }

    private function visibleReports(Request $request): Builder
    {
        $query = \App\Models\Report::query();
        $request->user()->constrainVisibleReports($query);

        return $query;
    }

    public function locations(Request $request): JsonResponse
    {
        return response()->json($this->locationOptions($this->validatedFilters($request)));
    }

    private function locationOptions(array $filters): array
    {
        $states = $filters['state_id'];
        $municipalities = Municipality::query()->with('state:id,name')
            ->when($states, fn (Builder $query) => $query->whereIn('state_id', $states))
            ->orderBy('name')->orderBy('id')->get(['id', 'name', 'state_id']);
        $parishes = Parish::query()->with('municipality:id,name,state_id', 'municipality.state:id,name')
            ->whereIn('municipality_id', $municipalities->pluck('id'))
            ->when($filters['municipality_id'] ?? null, fn (Builder $query, int $id) => $query->where('municipality_id', $id))
            ->orderBy('name')->orderBy('id')->get(['id', 'name', 'municipality_id']);

        return [
            'municipalities' => $municipalities->map(fn ($item) => ['id' => $item->id, 'name' => $item->name.' — '.$item->state?->name]),
            'parishes' => $parishes->map(fn ($item) => ['id' => $item->id, 'name' => $item->name.' — '.$item->municipality?->name.' — '.$item->municipality?->state?->name]),
        ];
    }

    /** @param array<string, mixed> $filters */
    private function filteredBeneficiaries(Request $request, array $filters): Builder
    {
        return Beneficiary::query()
            ->whereHas('report', function (Builder $query) use ($request, $filters): void {
                $request->user()->constrainVisibleReports($query);
                $query
                    ->when($filters['attention_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('report_date', '>=', $date))
                    ->when($filters['attention_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('report_date', '<=', $date))
                    ->when($filters['state_id'], fn (Builder $q, array $ids) => $q->whereIn('state_id', $ids))
                    ->when($filters['municipality_id'] ?? null, fn (Builder $q, int $id) => $q->where('municipality_id', $id))
                    ->when($filters['parish_id'] ?? null, fn (Builder $q, int $id) => $q->where('parish_id', $id))
                    ->when($filters['installation_type'] ?? null, fn (Builder $q, string $type) => $q->where('installation_type', $type))
                    ->when($filters['place_name'] ?? null, fn (Builder $q, string $place) => $q->where('place_name', $place))
                    ->when($filters['sector_id'] ?? null, fn (Builder $q, int $id) => $q->where('sector_id', $id))
                    ->when($filters['indicador_id'] ?? null, fn (Builder $q, int $id) => $q->whereHas(
                        'indicadorProyecto',
                        fn (Builder $assignment) => $assignment->where('indicador_id', $id),
                    ));
            })
            ->when($filters['registered_from'] ?? null, fn (Builder $q, string $date) => $q->whereDate('beneficiaries.created_at', '>=', $date))
            ->when($filters['registered_to'] ?? null, fn (Builder $q, string $date) => $q->whereDate('beneficiaries.created_at', '<=', $date))
            ->when(($filters['age_from'] ?? '') !== '', fn (Builder $q) => $q->where('age', '>=', (int) $filters['age_from']))
            ->when(($filters['age_to'] ?? '') !== '', fn (Builder $q) => $q->where('age', '<=', (int) $filters['age_to']))
            ->when($filters['age_group'] ?? null, function (Builder $q, string $group): void {
                $range = self::AGE_GROUPS[$group];
                $q->whereBetween('age', [$range['from'], $range['to']]);
            })
            ->when($filters['sex'] ?? null, fn (Builder $q, string $sex) => $q->where('sex', $sex))
            ->when(array_key_exists('is_recurrent', $filters) && $filters['is_recurrent'] !== null && $filters['is_recurrent'] !== '', fn (Builder $q) => $q->where('is_recurrent', (bool) $filters['is_recurrent']))
            ->when(($filters['reported'] ?? '') === '1', fn (Builder $q) => $q->whereNotNull('reported_at'))
            ->when(($filters['reported'] ?? '') === '0', fn (Builder $q) => $q->whereNull('reported_at'));
    }

    /** @return array<string, mixed> */
    private function validatedFilters(Request $request): array
    {
        $input = $request->all();
        // Continue accepting bookmarked URLs with a single state_id.
        if (! is_array($input['state_id'] ?? null)) {
            $input['state_id'] = filled($input['state_id'] ?? null) ? [$input['state_id']] : [];
        }

        // El grupo etario y el rango manual representan el mismo criterio. Si una
        // URL antigua contiene ambos, el grupo etario tiene prioridad para evitar
        // aplicar dos rangos de edad simultáneamente.
        if (filled($input['age_group'] ?? null)) {
            $input['age_from'] = null;
            $input['age_to'] = null;
        }

        $filters = validator($input, [
            'attention_from' => ['nullable', 'date'],
            'attention_to' => ['nullable', 'date', 'after_or_equal:attention_from'],
            'registered_from' => ['nullable', 'date'],
            'registered_to' => ['nullable', 'date', 'after_or_equal:registered_from'],
            'age_from' => ['nullable', 'integer', 'min:0', 'max:120'],
            'age_to' => ['nullable', 'integer', 'min:0', 'max:120', 'gte:age_from'],
            'age_group' => ['nullable', Rule::in(array_keys(self::AGE_GROUPS))],
            'sex' => ['nullable', Rule::in(config('reports.beneficiary_options.sexes'))],
            'state_id' => ['array', 'max:100'],
            'state_id.*' => ['required', 'integer', 'distinct', 'exists:states,id'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'parish_id' => ['nullable', 'integer', 'exists:parishes,id'],
            'installation_type' => ['nullable', Rule::in(config('reports.installation_types'))],
            'place_name' => ['nullable', 'string', 'max:200'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'indicador_id' => ['nullable', 'integer', 'exists:indicadores,id'],
            'is_recurrent' => ['nullable', Rule::in(['0', '1', 0, 1])],
            'reported' => ['nullable', Rule::in(['0', '1', 0, 1])],
        ])->validate();

        $filters['state_id'] = array_map('intval', $filters['state_id']);
        if (! empty($filters['municipality_id']) && $filters['state_id'] && ! Municipality::query()
            ->whereKey($filters['municipality_id'])->whereIn('state_id', $filters['state_id'])->exists()) {
            throw ValidationException::withMessages(['municipality_id' => 'El municipio no pertenece a los estados seleccionados.']);
        }
        if (! empty($filters['parish_id']) && ! Parish::query()->whereKey($filters['parish_id'])
            ->when($filters['municipality_id'] ?? null, fn (Builder $q, int $id) => $q->where('municipality_id', $id))
            ->when($filters['state_id'], fn (Builder $q, array $ids) => $q->whereHas('municipality', fn (Builder $m) => $m->whereIn('state_id', $ids)))
            ->exists()) {
            throw ValidationException::withMessages(['parish_id' => 'La parroquia no pertenece a los estados o al municipio seleccionados.']);
        }

        return $filters;
    }

    private function summary($beneficiaries): array
    {
        return [
            'beneficiaries' => $beneficiaries->count(),
            'attentions' => $beneficiaries->pluck('report_id')->unique()->count(),
            'women' => $beneficiaries->where('sex', 'Mujer')->count(),
            'men' => $beneficiaries->where('sex', 'Hombre')->count(),
            'average_age' => $beneficiaries->isEmpty() ? 0 : round((float) $beneficiaries->avg('age'), 1),
        ];
    }

    private function charts($beneficiaries): array
    {
        $ranges = collect(self::AGE_GROUPS);
        $countRange = fn (string $sex, array $range): int => $beneficiaries->filter(
            fn (Beneficiary $beneficiary): bool => $beneficiary->sex === $sex && $beneficiary->age >= $range['from'] && $beneficiary->age <= $range['to'],
        )->count();

        $reports = $beneficiaries->pluck('report')->filter();
        $trend = $beneficiaries->groupBy(fn (Beneficiary $beneficiary): string => $beneficiary->report?->report_date?->format('Y-m-d') ?? '')
            ->filter(fn ($items, string $date): bool => $date !== '')
            ->sortKeys();

        return [
            'sex' => [
                'labels' => ['Hombres', 'Mujeres'],
                'values' => [$beneficiaries->where('sex', 'Hombre')->count(), $beneficiaries->where('sex', 'Mujer')->count()],
            ],
            'ages' => [
                'labels' => $ranges->pluck('label')->values(),
                'men' => $ranges->map(fn (array $range): int => $countRange('Hombre', $range))->values(),
                'women' => $ranges->map(fn (array $range): int => $countRange('Mujer', $range))->values(),
            ],
            'attention_types' => [
                'labels' => $reports->groupBy('installation_type')->map->count()->sortDesc()->keys()->values(),
                'values' => $reports->groupBy('installation_type')->map->count()->sortDesc()->values(),
            ],
            'states' => [
                'labels' => $reports->groupBy(fn ($report): string => $report->state?->name ?? 'Sin estado')->map->count()->sortDesc()->take(10)->keys()->values(),
                'values' => $reports->groupBy(fn ($report): string => $report->state?->name ?? 'Sin estado')->map->count()->sortDesc()->take(10)->values(),
            ],
            'trend' => [
                'labels' => $trend->keys()->values(),
                'men' => $trend->map(fn ($items): int => $items->where('sex', 'Hombre')->count())->values(),
                'women' => $trend->map(fn ($items): int => $items->where('sex', 'Mujer')->count())->values(),
            ],
        ];
    }
}
