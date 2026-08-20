<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\StoreBeneficiaryEntryRequest;
use App\Http\Requests\UpdateBeneficiaryRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Models\Beneficiary;
use App\Models\Evidence;
use App\Models\Municipality;
use App\Models\PlaceName;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use App\Models\Proyecto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $isCoordinator = $request->user()->isCoordinator();
        $reports = collect();
        $beneficiaries = collect();

        if ($isCoordinator) {
            $beneficiaries = $this->filteredBeneficiaries($request)
                ->with([
                    'report.user',
                    'report.state',
                    'report.municipality',
                    'report.parish',
                    'report.sector', 'report.activity', 'report.proyecto', 'report.indicadorProyecto.indicador',
                ])
                ->latest('created_at')
                ->latest('id')
                ->get();
        } else {
            $reports = $this->filteredReports($request)
                ->with(['user', 'state', 'municipality', 'parish', 'sector', 'activity', 'proyecto', 'indicadorProyecto.indicador'])
                ->withCount('beneficiaries')
                ->withCount(['beneficiaries as unreported_beneficiaries_count' => fn (Builder $query) => $query->whereNull('reported_at')])
                ->latest('created_at')
                ->latest('id')
                ->get();
        }

        return view('reports.index', [
            'reports' => $reports,
            'beneficiaries' => $beneficiaries,
            'states' => State::orderBy('name')->get(['id', 'name']),
            'isCoordinator' => $isCoordinator,
            'filters' => $request->only(['state_id', 'reported', 'from', 'to']),
        ]);
    }

    public function create(Request $request): View
    {
        $projects = $this->availableProjects($request);
        $selectedProjectId = old('proyecto_id', $projects->first()?->id);
        $locations = $this->availableLocations($request, $projects);

        return view('reports.create', [
            'projects' => $projects,
            'projectIndicatorOptions' => $this->projectIndicatorOptions($projects),
            'selectedProjectId' => $selectedProjectId,
            'organizations' => config('reports.organizations'),
            'states' => $locations['states'],
            'projectLocationOptions' => $this->projectLocationOptions($projects),
            'communityLocation' => false,
            'communityMunicipalities' => collect(),
            'communityParishes' => collect(),
            'placeNames' => $locations['placeNames'],
            'beneficiaryOptions' => config('reports.beneficiary_options'),
            'user' => $request->user(),
        ]);
    }

    public function edit(Request $request, Report $report): View
    {
        $this->ensureEditable($request, $report);
        $report->load(['beneficiaries', 'evidences', 'serviciosActividad', 'indicadorProyecto']);
        $requestedBeneficiaryId = $request->integer('beneficiary');
        $editBeneficiaryId = $requestedBeneficiaryId && $report->beneficiaries->contains('id', $requestedBeneficiaryId)
            ? $requestedBeneficiaryId
            : $report->beneficiaries->first()?->id;

        $projects = $this->availableProjects($request, $report->proyecto_id);
        $locations = $this->availableLocations($request, $projects);
        $selectedProjectId = old('proyecto_id', $report->proyecto_id);
        $communityLocation = ! PlaceName::where('name', $report->place_name)->exists();

        return view('reports.create', [
            'report' => $report,
            'projects' => $projects,
            'projectIndicatorOptions' => $this->projectIndicatorOptions($projects),
            'selectedProjectId' => $selectedProjectId,
            'organizations' => config('reports.organizations'),
            'states' => $locations['states'],
            'projectLocationOptions' => $this->projectLocationOptions($projects),
            'communityLocation' => $communityLocation,
            'communityMunicipalities' => $communityLocation
                ? State::find($report->state_id)?->municipalities()->orderBy('name')->get(['id', 'name']) ?? collect()
                : collect(),
            'communityParishes' => $communityLocation
                ? Municipality::find($report->municipality_id)?->parishes()->orderBy('name')->get(['id', 'name', 'latitude', 'longitude']) ?? collect()
                : collect(),
            'placeNames' => $locations['placeNames'],
            'beneficiaryOptions' => config('reports.beneficiary_options'),
            'user' => $request->user(),
            'editBeneficiaryId' => $editBeneficiaryId,
        ]);
    }

    public function update(UpdateReportRequest $request, Report $report): RedirectResponse|JsonResponse
    {
        $this->ensureEditable($request, $report);
        $data = $request->validated();
        $serviceIds = $data['servicio_actividad_ids'] ?? [];
        unset($data['servicio_actividad_ids'], $data['sector_proyecto_id']);
        unset($data['evidence_1'], $data['evidence_2'], $data['evidence_3']);

        DB::transaction(function () use ($request, $report, $data, $serviceIds): void {
            $report->update($data);
            $report->serviciosActividad()->sync($serviceIds);
            $this->storeEvidence($report, $request);
            $this->syncBeneficiarySummary($report);
        });

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Registro actualizado correctamente.',
                'url' => route('reports.show', $report),
            ]);
        }

        return redirect()->route('reports.show', $report)->with('success', 'Registro actualizado correctamente.');
    }

    public function store(StoreReportRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $beneficiaries = $data['beneficiaries'];
        $serviceIds = $data['servicio_actividad_ids'] ?? [];
        unset($data['beneficiaries'], $data['servicio_actividad_ids'], $data['sector_proyecto_id'], $data['evidence_1'], $data['evidence_2'], $data['evidence_3']);

        $summary = $this->beneficiarySummary($beneficiaries);
        $data['user_id'] = $request->user()->id;
        $data['total_beneficiaries'] = $summary['total'];
        $data['recurrence_status'] = $summary['recurrence_status'];
        $data['beneficiary_breakdown'] = $summary['breakdown'];
        $data['people_with_disabilities'] = $summary['people_with_disabilities'];
        $data['indigenous_people'] = $summary['indigenous_people'];
        $data['pregnant_or_lactating_women'] = $summary['pregnant_or_lactating_women'];

        $report = DB::transaction(function () use ($data, $beneficiaries, $request, $serviceIds): Report {
            $report = Report::create($data);
            $report->serviciosActividad()->sync($serviceIds);
            $report->beneficiaries()->createMany($beneficiaries);

            foreach ([1, 2, 3] as $slot) {
                $file = $request->file("evidence_{$slot}");
                if (! $file) {
                    continue;
                }

                $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
                $path = Storage::disk('local')->putFileAs("reports/{$report->id}", $file, $filename);
                $report->evidences()->create([
                    'slot' => $slot,
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                ]);
            }

            return $report;
        });

        return redirect()->route('reports.show', $report)->with('success', 'Registro enviado correctamente para su seguimiento.');
    }

    public function storeBeneficiary(StoreBeneficiaryEntryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $beneficiaryData = $data['beneficiary'];
        $reportId = $data['report_id'] ?? null;
        $serviceIds = $data['servicio_actividad_ids'] ?? [];
        unset($data['beneficiary'], $data['report_id'], $data['servicio_actividad_ids'], $data['sector_proyecto_id'], $data['evidence_1'], $data['evidence_2'], $data['evidence_3']);

        [$report, $beneficiary, $summary, $createdReport] = DB::transaction(function () use ($request, $data, $beneficiaryData, $reportId, $serviceIds): array {
            if ($reportId) {
                $report = Report::findOrFail($reportId);
                $this->ensureEditable($request, $report);
                abort_unless($this->headersMatch($report, $data, $serviceIds), 409, 'Los encabezados cambiaron. Guarde el beneficiario como un nuevo registro.');

                $report->update([
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                    'altitude' => $data['altitude'] ?? null,
                    'gps_accuracy' => $data['gps_accuracy'] ?? null,
                    'activity_details' => $data['activity_details'] ?? null,
                    'qualitative_notes' => $data['qualitative_notes'] ?? null,
                ]);
                $createdReport = false;
            } else {
                $summary = $this->beneficiarySummary([$beneficiaryData]);
                $report = Report::create(array_merge($data, [
                    'user_id' => $request->user()->id,
                    'total_beneficiaries' => $summary['total'],
                    'recurrence_status' => $summary['recurrence_status'],
                    'beneficiary_breakdown' => $summary['breakdown'],
                    'people_with_disabilities' => $summary['people_with_disabilities'],
                    'indigenous_people' => $summary['indigenous_people'],
                    'pregnant_or_lactating_women' => $summary['pregnant_or_lactating_women'],
                ]));
                $report->serviciosActividad()->sync($serviceIds);
                $createdReport = true;
            }

            $beneficiary = $report->beneficiaries()->create($beneficiaryData);
            $this->storeEvidence($report, $request);
            $summary = $this->syncBeneficiarySummary($report);

            return [$report->fresh(), $beneficiary->fresh(), $summary, $createdReport];
        });

        return response()->json([
            'message' => $createdReport ? 'Registro creado y beneficiario guardado correctamente.' : 'Beneficiario guardado correctamente.',
            'report' => [
                'id' => $report->id,
                'url' => route('reports.show', $report),
                'total_beneficiaries' => $report->total_beneficiaries,
            ],
            'beneficiary' => $beneficiary,
            'summary' => $summary,
        ], $createdReport ? 201 : 200);
    }

    public function updateBeneficiary(UpdateBeneficiaryRequest $request, Beneficiary $beneficiary): JsonResponse
    {
        $report = $beneficiary->report;
        $this->ensureEditable($request, $report);
        $beneficiary->update($request->validated());
        $summary = $this->syncBeneficiarySummary($report);

        return response()->json([
            'message' => 'Beneficiario actualizado correctamente.',
            'beneficiary' => $beneficiary->fresh(),
            'summary' => $summary,
        ]);
    }

    public function destroyBeneficiary(Request $request, Beneficiary $beneficiary): JsonResponse
    {
        $report = $beneficiary->report;
        $this->ensureEditable($request, $report);

        if ($report->beneficiaries()->count() === 1 && ! $request->user()->isAdministrator()) {
            return response()->json([
                'message' => 'Solo un administrador puede eliminar el último beneficiario, ya que esta acción elimina el registro completo.',
            ], 403);
        }

        $beneficiary->delete();

        if (! $report->beneficiaries()->exists()) {
            Storage::disk('local')->deleteDirectory("reports/{$report->id}");
            $report->delete();

            return response()->json([
                'message' => 'Beneficiario eliminado. Como era el único, también se eliminó el registro.',
                'report_deleted' => true,
                'summary' => $this->emptyBeneficiarySummary(),
            ]);
        }

        return response()->json([
            'message' => 'Beneficiario eliminado correctamente.',
            'report_deleted' => false,
            'summary' => $this->syncBeneficiarySummary($report),
        ]);
    }

    public function show(Request $request, Report $report): View
    {
        $this->ensureVisible($request, $report);
        $report->load(['user', 'state', 'municipality', 'parish', 'sector', 'activity', 'proyecto', 'indicadorProyecto.indicador', 'actividadIndicador.actividad', 'serviciosActividad.servicio', 'beneficiaries', 'evidences', 'reviewer']);

        return view('reports.show', [
            'report' => $report,
            'isCoordinator' => $request->user()->isCoordinator(),
            'canEditBeneficiaries' => (
                $report->user_id === $request->user()->id
                || $request->user()->isAdministrator()
            ) && $report->status !== 'reviewed',
            'canDeleteReport' => $request->user()->isAdministrator(),
            'beneficiaryOptions' => config('reports.beneficiary_options'),
            'beneficiaryEditData' => $report->beneficiaries->keyBy('id')->map(fn (Beneficiary $beneficiary): array => [
                'id' => $beneficiary->id,
                'full_name' => $beneficiary->full_name,
                'age' => $beneficiary->age,
                'sex' => $beneficiary->sex,
                'national_id' => $beneficiary->national_id,
                'phone' => $beneficiary->phone,
                'disability' => $beneficiary->disability ?: 'Ninguna',
                'ethnicity' => $beneficiary->ethnicity ?: 'Ninguna',
                'pregnant_lactating' => $beneficiary->pregnant_lactating ?: 'Ninguna',
                'is_recurrent' => $beneficiary->is_recurrent,
            ]),
        ]);
    }

    public function destroy(Request $request, Report $report): RedirectResponse
    {
        abort_unless($request->user()->isAdministrator(), 403);

        $reportId = $report->id;

        DB::transaction(function () use ($report): void {
            $report->delete();
        });

        Storage::disk('local')->deleteDirectory("reports/{$reportId}");

        return redirect()->route('reports.index')->with('success', 'El registro y sus beneficiarios fueron eliminados correctamente.');
    }

    public function review(Request $request, Report $report): RedirectResponse
    {
        abort_unless($request->user()->isCoordinator(), 403);
        $this->ensureVisible($request, $report);
        $report->update([
            'status' => 'reviewed',
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
        ]);

        return back()->with('success', 'El registro fue marcado como revisado.');
    }

    public function downloadEvidence(Request $request, Evidence $evidence): StreamedResponse
    {
        $this->ensureVisible($request, $evidence->report);
        abort_unless(Storage::disk('local')->exists($evidence->path), 404);

        return Storage::disk('local')->download($evidence->path, $evidence->original_name);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->isCoordinator(), 403);
        $beneficiaries = $this->filteredBeneficiaries($request)
            ->with(['report.state', 'report.municipality', 'report.parish', 'report.sector', 'report.activity'])
            ->latest('created_at')
            ->latest('id')
            ->get();

        return response()->streamDownload(function () use ($beneficiaries): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'ID registro', 'Fecha', 'Organización', 'Estado', 'Municipio', 'Parroquia', 'Sector', 'Actividad',
                'Nombre y apellido', 'Edad', 'Sexo', 'Cédula', 'Teléfono', 'Discapacidad', 'Indígena',
                'Embarazada o lactante', 'Recurrente', 'Reportado', 'Fecha de reporte', 'Estado de revisión',
            ]);

            foreach ($beneficiaries as $beneficiary) {
                $report = $beneficiary->report;

                fputcsv($out, [
                    $report->id, $report->report_date->format('Y-m-d'), $report->organization,
                    $report->state->name, $report->municipality->name, $report->parish->name,
                    $report->sector->name, $report->activity->title, $beneficiary->full_name,
                    $beneficiary->age, $beneficiary->sex, $beneficiary->national_id, $beneficiary->phone,
                    $beneficiary->disability, $beneficiary->ethnicity, $beneficiary->pregnant_lactating,
                    $beneficiary->is_recurrent ? 'Sí' : 'No',
                    $beneficiary->reported_at ? 'Sí' : 'No',
                    $beneficiary->reported_at?->format('Y-m-d'), $report->status,
                ]);
            }
            fclose($out);
        }, 'registro-respuesta-asonacop-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function availableProjects(Request $request, ?int $includeProjectId = null)
    {
        return Proyecto::query()
            ->with(['donante', 'estados:id', 'municipios:id', 'asignacionesIndicadores' => fn ($query) => $query
                ->with([
                    'indicador',
                    'asignacionSector.sector',
                    'asignacionesActividades' => fn ($activities) => $activities
                        ->with(['actividad', 'asignacionesServicios' => fn ($services) => $services->with('servicio')->where('estatus', true)])
                        ->where('estatus', true),
                ])->whereNotNull('sector_proyecto_id')->where('estatus', true)])
            ->where(function ($query) use ($includeProjectId): void {
                $query->where('estatus', true);
                if ($includeProjectId) $query->orWhere('proyectos.id', $includeProjectId);
            })
            ->when(! $request->user()->isAdministrator(), fn ($query) => $query->whereHas('users', fn ($users) => $users->whereKey($request->user()->id)))
            ->orderBy('codigo')->get();
    }

    private function availableLocations(Request $request, $projects): array
    {
        $user = $request->user();
        $basePlaces = PlaceName::query()
            ->with(['state:id,name', 'municipality:id,name', 'parish:id,name'])
            ->whereNotNull('state_id')->whereNotNull('municipality_id')
            ->whereNotNull('parish_id')->whereNotNull('installation_type')->orderBy('name');

        if ($projects->isEmpty()) {
            return [
                'states' => State::orderBy('name')->get(['id', 'name']),
                'placeNames' => $basePlaces->get([
                    'id', 'name', 'state_id', 'municipality_id', 'parish_id', 'installation_type',
                    'latitude', 'longitude', 'altitude', 'gps_accuracy',
                ]),
            ];
        }

        $projectStateIds = $projects->flatMap->estados->pluck('id')->unique();
        $municipalitiesByState = Municipality::whereIn('state_id', $projectStateIds)
            ->get(['id', 'state_id'])->groupBy('state_id');
        $projectMunicipalityIds = $projects->flatMap(fn (Proyecto $project) => $project->municipios->isEmpty()
            ? $project->estados->flatMap(fn ($state) => $municipalitiesByState->get($state->id, collect()))->pluck('id')
            : $project->municipios->pluck('id')
        )->unique();
        $municipalityStates = $municipalitiesByState->flatten()->keyBy('id');

        if (! $user->isAdministrator() && ! $user->countrywide_access) {
            $user->loadMissing(['assignedStates:id', 'assignedMunicipalities:id,state_id']);
            $fullStateIds = $user->assignedStates->pluck('id');
            $specificMunicipalityIds = $user->assignedMunicipalities->pluck('id');
            $projectStateIds = $projectStateIds->filter(fn ($id) => $fullStateIds->contains($id)
                || $user->assignedMunicipalities->contains('state_id', $id));
            $projectMunicipalityIds = $projectMunicipalityIds->filter(fn ($id) => $specificMunicipalityIds->contains($id)
                || $fullStateIds->contains($municipalityStates->get($id)?->state_id));
        }

        $placeNames = $basePlaces
            ->whereIn('state_id', $projectStateIds)
            ->whereIn('municipality_id', $projectMunicipalityIds)
            ->get([
                'id', 'name', 'state_id', 'municipality_id', 'parish_id', 'installation_type',
                'latitude', 'longitude', 'altitude', 'gps_accuracy',
            ]);

        return [
            'states' => State::whereIn('id', $projectStateIds)->orderBy('name')->get(['id', 'name']),
            'placeNames' => $placeNames,
        ];
    }

    private function projectLocationOptions($projects): array
    {
        return $projects->mapWithKeys(fn (Proyecto $project) => [$project->id => [
            'states' => $project->estados->pluck('id')->values()->all(),
            'municipalities' => $project->municipios->pluck('id')->values()->all(),
            'allStateMunicipalities' => $project->municipios->isEmpty(),
        ]])->all();
    }

    private function projectIndicatorOptions($projects): array
    {
        return $projects->mapWithKeys(function (Proyecto $project): array {
            return [$project->id => $project->asignacionesIndicadores->map(function ($assignment): array {
                return [
                    'id' => $assignment->id,
                    'sectorProjectId' => $assignment->sector_proyecto_id,
                    'sectorId' => $assignment->asignacionSector?->sector_id,
                    'sectorCode' => $assignment->asignacionSector?->sector?->codigo,
                    'sectorTitle' => $assignment->asignacionSector?->sector?->descripcion
                        ?: $assignment->asignacionSector?->sector?->name,
                    'title' => $assignment->indicador->descripcion,
                    'unit' => $assignment->indicador->unidad_conteo,
                    'ageFrom' => $assignment->indicador->edad_desde,
                    'ageTo' => $assignment->indicador->edad_hasta,
                    'activities' => $assignment->asignacionesActividades->map(function ($projectActivity): array {
                        return [
                            'id' => $projectActivity->id,
                            'title' => $projectActivity->actividad->codigo.' — '.$projectActivity->actividad->descripcion,
                            'services' => $projectActivity->asignacionesServicios->map(fn ($projectService): array => [
                                'id' => $projectService->id,
                                'title' => $projectService->servicio->nombre,
                            ])->values()->all(),
                        ];
                    })->values()->all(),
                ];
            })->values()->all()];
        })->all();
    }

    private function filteredReports(Request $request): Builder
    {
        $query = Report::query();
        $request->user()->constrainVisibleReports($query);

        $reported = $request->input('reported');
        if ($reported === '1') {
            $query->whereHas('beneficiaries')
                ->whereDoesntHave('beneficiaries', fn (Builder $beneficiaries) => $beneficiaries->whereNull('reported_at'));
        }
        if ($reported === '0') {
            $query->whereHas('beneficiaries', fn (Builder $beneficiaries) => $beneficiaries->whereNull('reported_at'));
        }

        return $query
            ->when($request->integer('state_id'), fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
            ->when($request->input('from'), fn (Builder $query, string $from) => $query->whereDate('report_date', '>=', $from))
            ->when($request->input('to'), fn (Builder $query, string $to) => $query->whereDate('report_date', '<=', $to));
    }

    private function filteredBeneficiaries(Request $request): Builder
    {
        $reported = (string) $request->input('reported', '');

        return Beneficiary::query()
            ->whereHas('report', function (Builder $reports) use ($request): void {
                $request->user()->constrainVisibleReports($reports);
                $reports->when($request->integer('state_id'), fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
                    ->when($request->input('from'), fn (Builder $query, string $from) => $query->whereDate('report_date', '>=', $from))
                    ->when($request->input('to'), fn (Builder $query, string $to) => $query->whereDate('report_date', '<=', $to));
            })
            ->when($reported === '1', fn (Builder $query) => $query->whereNotNull('reported_at'))
            ->when($reported === '0', fn (Builder $query) => $query->whereNull('reported_at'));
    }

    private function ensureVisible(Request $request, Report $report): void
    {
        abort_unless($request->user()->canViewReport($report), 403);
    }

    /** @param array<string, mixed> $data */
    private function headersMatch(Report $report, array $data, array $serviceIds = []): bool
    {
        $fields = [
            'report_date', 'reporter_first_name', 'reporter_last_name', 'reporter_email',
            'organization', 'other_organization', 'state_id', 'municipality_id', 'parish_id',
            'installation_type', 'place_name', 'proyecto_id', 'indicador_proyecto_id', 'actividad_indicador_id', 'sector_id', 'activity_id',
        ];

        foreach ($fields as $field) {
            $current = $field === 'report_date' ? $report->report_date->format('Y-m-d') : $report->getAttribute($field);
            if ($this->headerValue($current) !== $this->headerValue($data[$field] ?? null)) {
                return false;
            }
        }

        $currentServiceIds = $report->serviciosActividad()->pluck('servicio_actividad.id')->map(fn ($id) => (int) $id)->sort()->values()->all();
        $incomingServiceIds = collect($serviceIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($currentServiceIds !== $incomingServiceIds) {
            return false;
        }

        return true;
    }

    private function headerValue(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function ensureEditable(Request $request, Report $report): void
    {
        abort_unless(
            $report->user_id === $request->user()->id || $request->user()->isAdministrator(),
            403
        );
        abort_if($report->status === 'reviewed', 409, 'No se puede modificar un registro revisado.');
    }

    private function storeEvidence(Report $report, Request $request): void
    {
        foreach ([1, 2, 3] as $slot) {
            $file = $request->file("evidence_{$slot}");
            if (! $file) {
                continue;
            }

            $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
            $path = Storage::disk('local')->putFileAs("reports/{$report->id}", $file, $filename);
            $existing = $report->evidences()->where('slot', $slot)->first();

            if ($existing) {
                Storage::disk('local')->delete($existing->path);
                $existing->update([
                    'original_name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => $file->getSize(),
                ]);

                continue;
            }

            $report->evidences()->create([
                'slot' => $slot,
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function syncBeneficiarySummary(Report $report): array
    {
        $beneficiaries = $report->beneficiaries()->get()->map(fn (Beneficiary $beneficiary) => $beneficiary->only([
            'full_name', 'age', 'sex', 'national_id', 'phone', 'disability', 'ethnicity', 'pregnant_lactating', 'is_recurrent',
        ]))->all();
        $summary = $this->beneficiarySummary($beneficiaries);

        $report->update([
            'total_beneficiaries' => $summary['total'],
            'recurrence_status' => $summary['recurrence_status'],
            'beneficiary_breakdown' => $summary['breakdown'],
            'people_with_disabilities' => $summary['people_with_disabilities'],
            'indigenous_people' => $summary['indigenous_people'],
            'pregnant_or_lactating_women' => $summary['pregnant_or_lactating_women'],
        ]);

        return $summary;
    }

    /** @return array<string, mixed> */
    private function emptyBeneficiarySummary(): array
    {
        return [
            'total' => 0,
            'recurrence_status' => 'no_recurrente',
            'people_with_disabilities' => 0,
            'indigenous_people' => 0,
            'pregnant_or_lactating_women' => 0,
            'breakdown' => ['source' => 'individual', 'by_sex' => [], 'by_age_range' => []],
        ];
    }

    /** @param array<int, array<string, mixed>> $beneficiaries */
    private function beneficiarySummary(array $beneficiaries): array
    {
        $total = count($beneficiaries);
        $recurrent = collect($beneficiaries)->filter(fn (array $beneficiary) => (bool) $beneficiary['is_recurrent'])->count();

        return [
            'total' => $total,
            'recurrence_status' => $recurrent === $total ? 'recurrente' : ($recurrent === 0 ? 'no_recurrente' : 'mixto'),
            'people_with_disabilities' => collect($beneficiaries)->filter(fn (array $beneficiary) => filled($beneficiary['disability'] ?? null) && $beneficiary['disability'] !== 'Ninguna')->count(),
            'indigenous_people' => collect($beneficiaries)->filter(fn (array $beneficiary) => filled($beneficiary['ethnicity'] ?? null) && $beneficiary['ethnicity'] !== 'Ninguna')->count(),
            'pregnant_or_lactating_women' => collect($beneficiaries)->where('pregnant_lactating', 'Sí')->count(),
            'breakdown' => [
                'source' => 'individual',
                'by_sex' => array_count_values(array_column($beneficiaries, 'sex')),
                'by_age_range' => [
                    '0_5' => collect($beneficiaries)->whereBetween('age', [0, 5])->count(),
                    '6_11' => collect($beneficiaries)->whereBetween('age', [6, 11])->count(),
                    '12_17' => collect($beneficiaries)->whereBetween('age', [12, 17])->count(),
                    '18_59' => collect($beneficiaries)->whereBetween('age', [18, 59])->count(),
                    '60_plus' => collect($beneficiaries)->where('age', '>=', 60)->count(),
                ],
            ],
        ];
    }
}
