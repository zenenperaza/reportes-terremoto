<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReportRequest;
use App\Models\Evidence;
use App\Models\Municipality;
use App\Models\Report;
use App\Models\Sector;
use App\Models\State;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
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
            ->latest('report_date')->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('reports.index', [
            'reports' => $reports,
            'states' => State::orderBy('name')->get(['id', 'name']),
            'isCoordinator' => $request->user()->isCoordinator(),
            'filters' => $request->only(['state_id', 'status', 'from', 'to']),
        ]);
    }

    public function create(Request $request): View
    {
        $state = State::find(old('state_id'));
        $municipality = Municipality::find(old('municipality_id'));
        $sector = Sector::find(old('sector_id'));

        return view('reports.create', [
            'states' => State::orderBy('name')->get(['id', 'name']),
            'municipalities' => $state ? $state->municipalities()->orderBy('name')->get(['id', 'name']) : collect(),
            'parishes' => $municipality ? $municipality->parishes()->orderBy('name')->get(['id', 'name']) : collect(),
            'sectors' => Sector::orderBy('sort_order')->get(['id', 'name']),
            'activities' => $sector ? $sector->activities()->where('active', true)->orderBy('sort_order')->get(['id', 'title']) : collect(),
            'organizations' => config('reports.organizations'),
            'installationTypes' => config('reports.installation_types'),
            'breakdownSchemes' => config('reports.breakdown_schemes'),
            'user' => $request->user(),
        ]);
    }

    public function store(StoreReportRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['beneficiary_breakdown']['scheme'] = $data['beneficiary_scheme'];
        unset($data['beneficiary_scheme'], $data['evidence_1'], $data['evidence_2'], $data['evidence_3']);

        $report = DB::transaction(function () use ($data, $request): Report {
            $report = Report::create($data);

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

        return redirect()->route('reports.show', $report)->with('success', 'Reporte enviado correctamente para su seguimiento.');
    }

    public function show(Request $request, Report $report): View
    {
        $this->ensureVisible($request, $report);
        $report->load(['user', 'state', 'municipality', 'parish', 'sector', 'activity', 'evidences', 'reviewer']);

        return view('reports.show', [
            'report' => $report,
            'breakdownSchemes' => config('reports.breakdown_schemes'),
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

        return back()->with('success', 'El reporte fue marcado como revisado.');
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
        $reports = $this->filteredReports($request)->with(['state', 'municipality', 'parish', 'sector', 'activity', 'user'])->get();

        return response()->streamDownload(function () use ($reports): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Fecha', 'Organización', 'Reportado por', 'Estado', 'Municipio', 'Parroquia', 'Sector', 'Actividad', 'Total beneficiarios', 'Estado del reporte']);
            foreach ($reports as $report) {
                fputcsv($out, [
                    $report->id, $report->report_date->format('Y-m-d'), $report->organization,
                    trim($report->reporter_first_name.' '.$report->reporter_last_name), $report->state->name,
                    $report->municipality->name, $report->parish->name, $report->sector->name,
                    $report->activity->title, $report->total_beneficiaries, $report->status,
                ]);
            }
            fclose($out);
        }, 'reporte-respuesta-unicef-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filteredReports(Request $request): Builder
    {
        $query = Report::query();
        if (! $request->user()->isCoordinator()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query
            ->when($request->integer('state_id'), fn (Builder $query, int $stateId) => $query->where('state_id', $stateId))
            ->when($request->input('status'), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->input('from'), fn (Builder $query, string $from) => $query->whereDate('report_date', '>=', $from))
            ->when($request->input('to'), fn (Builder $query, string $to) => $query->whereDate('report_date', '<=', $to));
    }

    private function ensureVisible(Request $request, Report $report): void
    {
        abort_unless($request->user()->isCoordinator() || $report->user_id === $request->user()->id, 403);
    }
}
