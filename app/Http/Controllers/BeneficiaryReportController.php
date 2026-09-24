<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Report;
use App\Models\Sector;
use App\Services\ReportLocationOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        if (($filters['included_from'] ?? null) || ($filters['included_to'] ?? null)) {
            $reports->whereHas('beneficiaries', function (Builder $beneficiaries) use ($filters): void {
                $beneficiaries
                    ->when($filters['included_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('beneficiaries.created_at', '>=', $date))
                    ->when($filters['included_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('beneficiaries.created_at', '<=', $date));
            });
        }

        $beneficiaryQuery = $this->filteredBeneficiaries($request, $filters);
        $pendingBeneficiaryCount = (clone $beneficiaryQuery)->whereNull('reported_at')->count();
        $beneficiaries = (clone $beneficiaryQuery)
            ->with([
                'report:id,place_name,activity_id,indicador_proyecto_id',
                'report.activity:id,title',
                'report.indicadorProyecto:id,indicador_id',
                'report.indicadorProyecto.indicador:id,descripcion',
            ])
            ->get(['id', 'report_id', 'age', 'sex', 'disability', 'ethnicity', 'pregnant_lactating']);
        $showReportedAt = $reported === true;
        $groupedBeneficiaries = $this->groupedBeneficiaries($beneficiaryQuery, $showReportedAt);

        $locations = $this->locationOptions($request, $filters);
        $optionReports = $this->optionReports($request, $filters);
        $optionSectorIds = (clone $optionReports)
            ->leftJoin('indicador_proyecto as filter_assignments', 'reports.indicador_proyecto_id', '=', 'filter_assignments.id')
            ->leftJoin('sector_proyecto as filter_sectors', 'filter_assignments.sector_proyecto_id', '=', 'filter_sectors.id')
            ->selectRaw('COALESCE(filter_sectors.sector_id, reports.sector_id)');

        return view('beneficiaries.summary', [
            'filters' => $filters,
            'summary' => $this->summary($beneficiaries),
            'summary345w' => $this->summary345w($beneficiaries),
            'reportCount' => $reports->count(),
            'pendingBeneficiaryCount' => $pendingBeneficiaryCount,
            'groupedBeneficiaries' => $groupedBeneficiaries,
            'showReportedAt' => $showReportedAt,
            'states' => $locations['states'],
            'municipalities' => $locations['municipalities'],
            'parishes' => $locations['parishes'],
            'sectors' => Sector::whereIn('id', $optionSectorIds)->orderBy('sort_order')->get(['id', 'name']),
            'indicatorOptions' => $this->indicatorOptions($request, $filters),
            'installationTypes' => (clone $optionReports)->whereNotNull('installation_type')->distinct()->orderBy('installation_type')->pluck('installation_type'),
            'places' => (clone $optionReports)->whereNotNull('place_name')->distinct()->orderBy('place_name')->pluck('place_name'),
            'recurrenceOptions' => $this->filteredBeneficiaries($request, ['reported' => $filters['reported'] ?? null])
                ->distinct()->pluck('is_recurrent')->map(fn ($value) => $value ? '1' : '0')->all(),
            'isConsolidated' => $request->user()->isCoordinator(),
        ]);
    }

    public function markAsReported(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->canMarkAsReported(),
            403,
            'No tiene permiso para actualizar beneficiarios a reportado.',
        );

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

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('exportar registros excel'), 403);

        $filters = $this->validatedFilters($request);
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->setTitle('Beneficiarios');

        $headers = [
            'ID', 'Estado', 'Municipio', 'Parroquia', 'Tipo de instalación', 'Nombre específico del lugar',
            'Latitud', 'Longitud', 'Código del proyecto', 'Sector programático', 'Código del indicador', 'Indicador a reportar',
            'Detalles adicionales de la actividad', 'Fecha de atención', 'Edad', 'Sexo', 'Discapacidad', 'Indígena',
            'Embarazada o lactante', 'Recurrente', 'Fecha de reporte', 'Fecha de inclusión', 'Usuario',
        ];
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        foreach ($headers as $index => $header) {
            $worksheet->setCellValueExplicit(
                Coordinate::stringFromColumnIndex($index + 1).'1',
                $header,
                DataType::TYPE_STRING,
            );
        }

        $worksheet->freezePane('A2');
        $worksheet->setAutoFilter("A1:{$lastColumn}1");
        $worksheet->getRowDimension(1)->setRowHeight(30);
        $worksheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2F6B57']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF8DA99D']]],
        ]);

        $widths = [8, 18, 20, 18, 26, 30, 13, 13, 22, 24, 24, 48, 48, 16, 10, 16, 18, 18, 22, 14, 18, 20, 28];
        foreach ($widths as $index => $width) {
            $worksheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
        }

        $rowNumber = 2;
        foreach ($this->exportBeneficiaries($request, $filters)->cursor() as $beneficiary) {
            $values = [
                $beneficiary->beneficiary_id,
                $beneficiary->state_name,
                $beneficiary->municipality_name,
                $beneficiary->parish_name,
                $beneficiary->installation_type,
                $beneficiary->place_name,
                $beneficiary->latitude,
                $beneficiary->longitude,
                $beneficiary->project_code,
                $beneficiary->sector_name,
                $beneficiary->indicator_code,
                $beneficiary->activity_title,
                $beneficiary->activity_details,
                $this->excelDate($beneficiary->report_date),
                $beneficiary->age,
                $beneficiary->sex,
                $beneficiary->disability,
                $beneficiary->ethnicity,
                $beneficiary->pregnant_lactating,
                $beneficiary->is_recurrent ? 'Sí' : 'No',
                $this->excelDate($beneficiary->reported_at),
                $this->excelDate($beneficiary->beneficiary_created_at, true),
                $beneficiary->user_name ?: trim("{$beneficiary->reporter_first_name} {$beneficiary->reporter_last_name}"),
            ];

            foreach ($values as $index => $value) {
                $cell = Coordinate::stringFromColumnIndex($index + 1).$rowNumber;

                if (in_array($index, [0, 14], true) && $value !== null && $value !== '') {
                    $worksheet->setCellValue($cell, (int) $value);
                } elseif (in_array($index, [6, 7], true) && $value !== null && $value !== '') {
                    $worksheet->setCellValue($cell, (float) $value);
                } elseif (in_array($index, [13, 20, 21], true) && $value !== null) {
                    $worksheet->setCellValue($cell, $value);
                } else {
                    $worksheet->setCellValueExplicit($cell, $this->spreadsheetText($value), DataType::TYPE_STRING);
                }
            }

            $rowNumber++;
        }

        $lastDataRow = max(2, $rowNumber - 1);
        $worksheet->getStyle("A2:{$lastColumn}{$lastDataRow}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP)->setWrapText(true);
        $worksheet->getStyle("N2:N{$lastDataRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $worksheet->getStyle("U2:U{$lastDataRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $worksheet->getStyle("V2:V{$lastDataRow}")->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm');

        $writer = new Xlsx($spreadsheet);
        $fileName = 'beneficiarios-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(
            static function () use ($writer): void {
                $writer->save('php://output');
            },
            $fileName,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
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
            ->when($filters['sector_id'] ?? null, function (Builder $query, int $sectorId): void {
                $query->where(function (Builder $sectors) use ($sectorId): void {
                    $sectors->whereHas('indicadorProyecto.asignacionSector', fn (Builder $sector) => $sector->where('sector_id', $sectorId))
                        ->orWhere(fn (Builder $legacy) => $legacy->whereDoesntHave('indicadorProyecto.asignacionSector')->where('sector_id', $sectorId));
                });
            })
            ->when($filters['indicator_filter'] ?? [], function (Builder $query, array $indicators): void {
                $projectIds = [];
                $legacyIds = [];
                foreach ($indicators as $indicator) {
                    [$type, $id] = explode(':', $indicator, 2);
                    if ($type === 'project') {
                        $projectIds[] = (int) $id;
                    } else {
                        $legacyIds[] = (int) $id;
                    }
                }
                // Match any selected indicator, without bypassing other filters or permissions.
                $query->where(function (Builder $indicators) use ($projectIds, $legacyIds): void {
                    $indicators->whereIn('indicador_proyecto_id', $projectIds)
                        ->orWhere(fn (Builder $legacy) => $legacy->whereNull('indicador_proyecto_id')->whereIn('activity_id', $legacyIds));
                });
            })
            ->when($filters['activity_id'] ?? null, fn (Builder $query, int $activityId) => $query->whereNull('indicador_proyecto_id')->where('activity_id', $activityId))
            ->when($filters['indicador_proyecto_id'] ?? null, fn (Builder $query, int $assignmentId) => $query->where('indicador_proyecto_id', $assignmentId));
    }

    private function indicatorOptions(Request $request, array $filters): Collection
    {
        // Use the same source as the report, not the old activities catalog.
        // Do not filter out inactive assignments that still have historical records.
        return $this->optionReports($request, $filters)
            ->leftJoin('indicador_proyecto as option_assignments', 'reports.indicador_proyecto_id', '=', 'option_assignments.id')
            ->leftJoin('indicadores as option_indicators', 'option_assignments.indicador_id', '=', 'option_indicators.id')
            ->leftJoin('sector_proyecto as option_sectors', 'option_assignments.sector_proyecto_id', '=', 'option_sectors.id')
            ->leftJoin('activities as option_activities', 'reports.activity_id', '=', 'option_activities.id')
            ->select([
                'reports.indicador_proyecto_id',
                DB::raw('CASE WHEN reports.indicador_proyecto_id IS NULL THEN reports.activity_id END as legacy_activity_id'),
                DB::raw('COALESCE(option_sectors.sector_id, reports.sector_id) as sector_id'),
                DB::raw('COALESCE(option_indicators.descripcion, option_activities.title) as title'),
                'option_indicators.codigo as code',
            ])->distinct()->orderBy('title')->toBase()->get()
            ->filter(fn ($option) => $option->indicador_proyecto_id || $option->legacy_activity_id)
            ->map(fn ($option) => [
                'value' => $option->indicador_proyecto_id ? 'project:'.$option->indicador_proyecto_id : 'legacy:'.$option->legacy_activity_id,
                'sector_id' => $option->sector_id,
                'label' => $option->indicador_proyecto_id
                    ? $option->code.': '.$option->title
                    : $option->title.' (registro anterior)',
            ])->values();
    }

    public function locations(Request $request): JsonResponse
    {
        $locations = $this->locationOptions($request, $this->validatedFilters($request));

        return response()->json(collect($locations)->map(
            fn ($items) => $items->map(fn ($item) => ['id' => $item->id, 'name' => $item->name])
        ));
    }

    private function locationOptions(Request $request, array $filters): array
    {
        return (new ReportLocationOptions)->get(
            $this->optionReports($request, $filters),
            filled($filters['state_id'] ?? null) ? [(int) $filters['state_id']] : [],
            filled($filters['municipality_id'] ?? null) ? (int) $filters['municipality_id'] : null,
        );
    }

    private function visibleReports(Request $request): Builder
    {
        $query = Report::query();
        $request->user()->constrainVisibleReports($query);

        return $this->excludeFlaggedIndicators($query);
    }

    /** Available filters depend on reporting status, not the other selected filters. */
    private function optionReports(Request $request, array $filters): Builder
    {
        $reported = $this->booleanFilter($filters, 'reported');

        return $this->visibleReports($request)->whereHas('beneficiaries', function (Builder $beneficiaries) use ($reported): void {
            if ($reported !== null) {
                $reported ? $beneficiaries->whereNotNull('reported_at') : $beneficiaries->whereNull('reported_at');
            }
        });
    }

    private function excludeFlaggedIndicators(Builder $query): Builder
    {
        // Keep legacy reports without a project indicator; exclude only explicitly flagged indicators.
        return $query->whereDoesntHave('indicadorProyecto.indicador',
            fn (Builder $indicator) => $indicator->where('excluir_reporte_beneficiarios', true)
        );
    }

    /** @return array<string, mixed> */
    private function validatedFilters(Request $request): array
    {
        $input = $request->all();
        // The explicit selector overrides old query-string filters, including "Todos".
        // Keep old single-selection links compatible with the new array selector.
        if ($request->exists('indicator_filter')) {
            unset($input['activity_id'], $input['indicador_proyecto_id']);
            $selected = is_array($input['indicator_filter']) ? $input['indicator_filter'] : [$input['indicator_filter']];
            $input['indicator_filter'] = array_values(array_filter($selected, fn ($value) => $value !== null && $value !== ''));
        }

        $filters = Validator::make($input, [
            'indicator_filter' => ['sometimes', 'array', 'max:1000'],
            'indicator_filter.*' => ['bail', 'required', 'string', 'regex:/\A(project|legacy):([1-9][0-9]*)\z/',
                function (string $attribute, string $value, \Closure $fail): void {
                    [$type, $id] = explode(':', $value, 2);
                    if (! DB::table($type === 'project' ? 'indicador_proyecto' : 'activities')->where('id', $id)->exists()) {
                        $fail('El indicador seleccionado no existe.');
                    }
                },
            ],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'included_from' => ['nullable', 'date'],
            'included_to' => ['nullable', 'date', 'after_or_equal:included_from'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'municipality_id' => ['nullable', 'integer', 'exists:municipalities,id'],
            'parish_id' => ['nullable', 'integer', 'exists:parishes,id'],
            'installation_type' => ['nullable', Rule::in(config('reports.installation_types'))],
            'place_name' => ['nullable', 'string', 'max:200'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'activity_id' => ['nullable', 'integer', 'exists:activities,id'],
            'indicador_proyecto_id' => ['nullable', 'integer', 'exists:indicador_proyecto,id'],
            'is_recurrent' => ['nullable', Rule::in(['0', '1', 0, 1])],
            'reported' => ['nullable', Rule::in(['0', '1', 0, 1])],
        ])->validate();

        if (isset($filters['indicator_filter'])) {
            $filters['indicator_filter'] = array_values(array_unique($filters['indicator_filter']));
        }

        if (! $request->exists('reported')) {
            $filters['reported'] = '0';
        }

        return $filters;
    }

    /** @param array<string, mixed> $filters */
    private function filteredBeneficiaries(Request $request, array $filters): Builder
    {
        $beneficiaries = Beneficiary::query()
            ->whereHas('report', function (Builder $query) use ($request, $filters): void {
                $request->user()->constrainVisibleReports($query);
                $this->excludeFlaggedIndicators($query);
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

        $beneficiaries
            ->when($filters['included_from'] ?? null, fn (Builder $query, string $date) => $query->whereDate('beneficiaries.created_at', '>=', $date))
            ->when($filters['included_to'] ?? null, fn (Builder $query, string $date) => $query->whereDate('beneficiaries.created_at', '<=', $date));

        return $beneficiaries;
    }

    /** @param array<string, mixed> $filters */
    private function exportBeneficiaries(Request $request, array $filters): Builder
    {
        return (clone $this->filteredBeneficiaries($request, $filters))
            ->join('reports as export_reports', 'beneficiaries.report_id', '=', 'export_reports.id')
            ->join('states as export_states', 'export_reports.state_id', '=', 'export_states.id')
            ->join('municipalities as export_municipalities', 'export_reports.municipality_id', '=', 'export_municipalities.id')
            ->join('parishes as export_parishes', 'export_reports.parish_id', '=', 'export_parishes.id')
            ->leftJoin('sectors as export_sectors', 'export_reports.sector_id', '=', 'export_sectors.id')
            ->leftJoin('activities as export_activities', 'export_reports.activity_id', '=', 'export_activities.id')
            ->leftJoin('proyectos as export_projects', 'export_reports.proyecto_id', '=', 'export_projects.id')
            ->leftJoin('indicador_proyecto as export_indicator_projects', 'export_reports.indicador_proyecto_id', '=', 'export_indicator_projects.id')
            ->leftJoin('sector_proyecto as export_sector_projects', 'export_indicator_projects.sector_proyecto_id', '=', 'export_sector_projects.id')
            ->leftJoin('sectors as export_project_sectors', 'export_sector_projects.sector_id', '=', 'export_project_sectors.id')
            ->leftJoin('indicadores as export_indicators', 'export_indicator_projects.indicador_id', '=', 'export_indicators.id')
            ->leftJoin('users as export_users', 'export_reports.user_id', '=', 'export_users.id')
            ->select([
                'beneficiaries.id as beneficiary_id', 'beneficiaries.full_name', 'beneficiaries.age', 'beneficiaries.sex',
                'beneficiaries.national_id', 'beneficiaries.phone', 'beneficiaries.disability', 'beneficiaries.ethnicity',
                'beneficiaries.pregnant_lactating', 'beneficiaries.is_recurrent', 'beneficiaries.reported_at',
                'beneficiaries.created_at as beneficiary_created_at', 'export_states.name as state_name',
                'export_municipalities.name as municipality_name', 'export_parishes.name as parish_name',
                'export_reports.installation_type', 'export_reports.place_name', 'export_reports.latitude',
                'export_reports.longitude',
                'export_projects.codigo as project_code',
                DB::raw('COALESCE(export_project_sectors.name, export_sectors.name) as sector_name'),
                'export_indicators.codigo as indicator_code',
                DB::raw('COALESCE(export_indicators.descripcion, export_activities.title) as activity_title'),
                'export_reports.activity_details', 'export_reports.report_date', 'export_users.name as user_name',
                'export_reports.reporter_first_name', 'export_reports.reporter_last_name',
            ])
            ->orderByDesc('export_reports.report_date')
            ->orderBy('export_states.name')
            ->orderBy('export_municipalities.name')
            ->orderBy('export_parishes.name')
            ->orderBy('beneficiaries.id');
    }

    private function groupedBeneficiaries(Builder $beneficiaries, bool $includeReportedAt): Collection
    {
        $select = [
            'grouped_reports.report_date', 'states.id as state_id', 'states.name as state_name',
            'municipalities.id as municipality_id', 'municipalities.name as municipality_name',
            'parishes.id as parish_id', 'parishes.name as parish_name', 'grouped_reports.place_name',
            'grouped_reports.activity_id',
            'grouped_reports.indicador_proyecto_id',
            DB::raw("COALESCE(project_sectors.descripcion, project_sectors.name, report_sectors.descripcion, report_sectors.name, 'Sin sector') as project_sector_name"),
            DB::raw('COALESCE(indicadores.descripcion, activities.title) as activity_title'),
            DB::raw('COUNT(beneficiaries.id) as beneficiary_count'),
        ];
        $groupBy = [
            'grouped_reports.report_date', 'states.id', 'states.name', 'municipalities.id', 'municipalities.name',
            'parishes.id', 'parishes.name', 'grouped_reports.place_name',
            'grouped_reports.indicador_proyecto_id', 'grouped_reports.activity_id', 'indicators_assignment.id',
            'indicadores.descripcion', 'activities.id', 'activities.title',
            'project_sectors.id', 'project_sectors.descripcion', 'project_sectors.name',
            'report_sectors.id', 'report_sectors.descripcion', 'report_sectors.name',
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
            ->leftJoin('activities', 'grouped_reports.activity_id', '=', 'activities.id')
            ->leftJoin('indicador_proyecto as indicators_assignment', 'grouped_reports.indicador_proyecto_id', '=', 'indicators_assignment.id')
            ->leftJoin('sector_proyecto as project_sector_assignments', 'indicators_assignment.sector_proyecto_id', '=', 'project_sector_assignments.id')
            ->leftJoin('sectors as project_sectors', 'project_sector_assignments.sector_id', '=', 'project_sectors.id')
            ->leftJoin('sectors as report_sectors', 'grouped_reports.sector_id', '=', 'report_sectors.id')
            ->leftJoin('indicadores', 'indicators_assignment.indicador_id', '=', 'indicadores.id')
            ->select($select)
            ->groupBy($groupBy)
            ->orderByDesc('grouped_reports.report_date')
            ->orderBy('states.name')
            ->orderBy('municipalities.name')
            ->orderBy('parishes.name')
            ->orderByRaw('COALESCE(indicadores.descripcion, activities.title)')
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

    private function excelDate(mixed $value, bool $includeTime = false): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = Carbon::parse($value);

        return Date::PHPToExcel($includeTime ? $date : $date->startOfDay());
    }

    private function spreadsheetText(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return preg_match('/^[=+\\-@]/', $value) === 1 ? "'{$value}" : $value;
    }

    /** @param Collection<int, Beneficiary> $beneficiaries */
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
            'girls_6_9' => $count('Mujer', 6, 9),
            'boys_6_9' => $count('Hombre', 6, 9),
            'girls_10_11' => $count('Mujer', 10, 11),
            'boys_10_11' => $count('Hombre', 10, 11),
            'girls_12_14' => $count('Mujer', 12, 14),
            'boys_12_14' => $count('Hombre', 12, 14),
            'girls_15_17' => $count('Mujer', 15, 17),
            'boys_15_17' => $count('Hombre', 15, 17),
            'women_18_19' => $count('Mujer', 18, 19),
            'men_18_19' => $count('Hombre', 18, 19),
            'women_20_49' => $count('Mujer', 20, 49),
            'men_20_49' => $count('Hombre', 20, 49),
            'women_50_59' => $count('Mujer', 50, 59),
            'men_50_59' => $count('Hombre', 50, 59),
            'women_60_plus' => $count('Mujer', 60, null),
            'men_60_plus' => $count('Hombre', 60, null),
            'total' => $beneficiaries->count(),
            'disability' => $beneficiaries->filter(fn (Beneficiary $beneficiary) => filled($beneficiary->disability) && $beneficiary->disability !== 'Ninguna')->count(),
            'ethnicity' => $beneficiaries->filter(fn (Beneficiary $beneficiary) => filled($beneficiary->ethnicity) && $beneficiary->ethnicity !== 'Ninguna')->count(),
            'pregnancy' => $beneficiaries->where('pregnant_lactating', 'Sí')->count(),
        ];
    }

    /** @param Collection<int, Beneficiary> $beneficiaries */
    private function summary345w($beneficiaries): array
    {
        $ranges = [
            'girls_0_5' => ['Mujer', 0, 5],
            'boys_0_5' => ['Hombre', 0, 5],
            'girls_6_9' => ['Mujer', 6, 9],
            'boys_6_9' => ['Hombre', 6, 9],
            'girls_10_11' => ['Mujer', 10, 11],
            'boys_10_11' => ['Hombre', 10, 11],
            'boys_12_14' => ['Hombre', 12, 14],
            'girls_12_14' => ['Mujer', 12, 14],
            'boys_15_17' => ['Hombre', 15, 17],
            'girls_15_17' => ['Mujer', 15, 17],
            'women_18_59' => ['Mujer', 18, 59],
            'men_18_59' => ['Hombre', 18, 59],
            'women_60_plus' => ['Mujer', 60, null],
            'men_60_plus' => ['Hombre', 60, null],
        ];

        $breakdown = function ($items) use ($ranges): array {
            $values = [];
            foreach ($ranges as $key => [$sex, $minimumAge, $maximumAge]) {
                $values[$key] = $items->filter(
                    fn (Beneficiary $beneficiary): bool => $beneficiary->sex === $sex
                        && $beneficiary->age >= $minimumAge
                        && ($maximumAge === null || $beneficiary->age <= $maximumAge),
                )->count();
            }

            return $values;
        };

        $places = $beneficiaries
            ->groupBy(fn (Beneficiary $beneficiary): string => $beneficiary->report?->place_name ?: 'Lugar sin especificar')
            ->map(function ($placeBeneficiaries, string $place) use ($breakdown): array {
                $services = $placeBeneficiaries
                    ->groupBy(fn (Beneficiary $beneficiary): string => $beneficiary->report?->indicadorProyecto?->indicador?->descripcion
                        ?: $beneficiary->report?->activity?->title
                        ?: 'Actividad sin especificar')
                    ->map(fn ($serviceBeneficiaries, string $service): array => [
                        'name' => $service,
                        'values' => $breakdown($serviceBeneficiaries),
                        'total' => $serviceBeneficiaries->count(),
                    ])
                    ->values()
                    ->all();

                return [
                    'place' => $place,
                    'services' => $services,
                    'total' => $placeBeneficiaries->count(),
                ];
            })
            ->values()
            ->all();

        $values = $breakdown($beneficiaries);

        return [
            'values' => $values,
            'places' => $places,
            'total_nna' => array_sum(array_slice($values, 0, 10)),
            'total_adults' => array_sum(array_slice($values, 10)),
            'total' => $beneficiaries->count(),
        ];
    }
}
