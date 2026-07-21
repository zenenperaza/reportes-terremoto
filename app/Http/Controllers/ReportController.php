<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\StoreBeneficiaryEntryRequest;
use App\Http\Requests\UpdateBeneficiaryRequest;
use App\Models\Beneficiary;
use App\Models\Evidence;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
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
        $reports = $this->filteredReports($request)
            ->with(['user', 'state', 'municipality', 'parish', 'sector', 'activity'])
            ->withCount('beneficiaries')
            ->withCount(['beneficiaries as unreported_beneficiaries_count' => fn (Builder $query) => $query->whereNull('reported_at')])
            ->latest('created_at')->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('reports.index', [
            'reports' => $reports,
            'states' => State::orderBy('name')->get(['id', 'name']),
            'isCoordinator' => $request->user()->isCoordinator(),
            'filters' => $request->only(['state_id', 'reported', 'from', 'to']),
        ]);
    }

    public function create(Request $request): View
    {
        $state = State::find(old('state_id'));
        $municipality = Municipality::find(old('municipality_id'));
        $selectedSectorId = old('sector_id', Sector::where('slug', 'proteccion-ninez')->value('id'));
        $sector = Sector::find($selectedSectorId);

        return view('reports.create', [
            'states' => State::orderBy('name')->get(['id', 'name']),
            'municipalities' => $state ? $state->municipalities()->orderBy('name')->get(['id', 'name']) : collect(),
            'parishes' => $municipality ? $municipality->parishes()->orderBy('name')->get(['id', 'name']) : collect(),
            'sectors' => Sector::query()
                ->orderByRaw('CASE WHEN slug = ? THEN 0 ELSE 1 END', ['proteccion-ninez'])
                ->orderBy('sort_order')
                ->get(['id', 'name']),
            'selectedSectorId' => $selectedSectorId,
            'activities' => $sector ? $sector->activities()->where('active', true)->orderBy('sort_order')->get(['id', 'title']) : collect(),
            'organizations' => config('reports.organizations'),
            'installationTypes' => config('reports.installation_types'),
            'beneficiaryOptions' => config('reports.beneficiary_options'),
            'user' => $request->user(),
        ]);
    }

    public function store(StoreReportRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $beneficiaries = $data['beneficiaries'];
        unset($data['beneficiaries'], $data['evidence_1'], $data['evidence_2'], $data['evidence_3']);

        $summary = $this->beneficiarySummary($beneficiaries);
        $data['user_id'] = $request->user()->id;
        $data['total_beneficiaries'] = $summary['total'];
        $data['recurrence_status'] = $summary['recurrence_status'];
        $data['beneficiary_breakdown'] = $summary['breakdown'];
        $data['people_with_disabilities'] = $summary['people_with_disabilities'];
        $data['indigenous_people'] = $summary['indigenous_people'];
        $data['pregnant_or_lactating_women'] = $summary['pregnant_or_lactating_women'];

        $report = DB::transaction(function () use ($data, $beneficiaries, $request): Report {
            $report = Report::create($data);
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
        unset($data['beneficiary'], $data['report_id'], $data['evidence_1'], $data['evidence_2'], $data['evidence_3']);

        [$report, $beneficiary, $summary, $createdReport] = DB::transaction(function () use ($request, $data, $beneficiaryData, $reportId): array {
            if ($reportId) {
                $report = Report::findOrFail($reportId);
                $this->ensureEditable($request, $report);
                abort_unless($this->headersMatch($report, $data), 409, 'Los encabezados cambiaron. Guarde el beneficiario como un nuevo registro.');

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
        $report->load(['user', 'state', 'municipality', 'parish', 'sector', 'activity', 'beneficiaries', 'evidences', 'reviewer']);

        return view('reports.show', [
            'report' => $report,
            'isCoordinator' => $request->user()->isCoordinator(),
        ]);
    }

    public function review(Request $request, Report $report): RedirectResponse
    {
        abort_unless($request->user()->isCoordinator(), 403);
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
        $reports = $this->filteredReports($request)
            ->with(['state', 'municipality', 'parish', 'sector', 'activity', 'beneficiaries'])
            ->get();

        return response()->streamDownload(function () use ($reports): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'ID registro', 'Fecha', 'Organización', 'Estado', 'Municipio', 'Parroquia', 'Sector', 'Actividad',
                'Nombre y apellido', 'Edad', 'Sexo', 'Cédula', 'Teléfono', 'Discapacidad', 'Indígena',
                'Embarazada o lactante', 'Recurrente', 'Reportado', 'Fecha de reporte', 'Estado de revisión',
            ]);

            foreach ($reports as $report) {
                $beneficiaries = $report->beneficiaries->isNotEmpty() ? $report->beneficiaries : collect([null]);
                foreach ($beneficiaries as $beneficiary) {
                    fputcsv($out, [
                        $report->id, $report->report_date->format('Y-m-d'), $report->organization,
                        $report->state->name, $report->municipality->name, $report->parish->name,
                        $report->sector->name, $report->activity->title, $beneficiary?->full_name,
                        $beneficiary?->age, $beneficiary?->sex, $beneficiary?->national_id, $beneficiary?->phone,
                        $beneficiary?->disability, $beneficiary?->ethnicity, $beneficiary?->pregnant_lactating,
                        $beneficiary ? ($beneficiary->is_recurrent ? 'Sí' : 'No') : null,
                        $beneficiary ? ($beneficiary->reported_at ? 'Sí' : 'No') : null,
                        $beneficiary?->reported_at?->format('Y-m-d'), $report->status,
                    ]);
                }
            }
            fclose($out);
        }, 'registro-respuesta-asonacop-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredReports(Request $request): Builder
    {
        $query = Report::query();
        if (! $request->user()->isCoordinator()) {
            $query->where('user_id', $request->user()->id);
        }

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

    private function ensureVisible(Request $request, Report $report): void
    {
        abort_unless($request->user()->isCoordinator() || $report->user_id === $request->user()->id, 403);
    }

    /** @param array<string, mixed> $data */
    private function headersMatch(Report $report, array $data): bool
    {
        $fields = [
            'report_date', 'reporter_first_name', 'reporter_last_name', 'reporter_email',
            'organization', 'other_organization', 'state_id', 'municipality_id', 'parish_id',
            'installation_type', 'place_name', 'sector_id', 'activity_id',
        ];

        foreach ($fields as $field) {
            $current = $field === 'report_date' ? $report->report_date->format('Y-m-d') : $report->getAttribute($field);
            if ($this->headerValue($current) !== $this->headerValue($data[$field] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function headerValue(mixed $value): string
    {
        return trim((string) ($value ?? ''));
    }

    private function ensureEditable(Request $request, Report $report): void
    {
        abort_unless($report->user_id === $request->user()->id, 403);
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
