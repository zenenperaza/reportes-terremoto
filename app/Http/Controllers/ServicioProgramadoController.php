<?php

namespace App\Http\Controllers;

use App\Models\Actividad;
use App\Models\Beneficiary;
use App\Models\Indicador;
use App\Models\Proyecto;
use App\Models\Report;
use App\Models\Sector;
use App\Models\Servicio;
use App\Models\ServicioActividad;
use App\Services\ProgrammedServicesExcelExport;
use App\Support\ReportPeriod;
use App\Support\ReportPeriodDates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ServicioProgramadoController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->validatedFilters($request);
        $dateBounds = $this->dateBounds($request, $filters);
        ReportPeriodDates::validate($filters, $dateBounds, ['from' => 'attention', 'to' => 'attention']);
        $available = ServicioActividad::whereHas('reports', fn (Builder $reports) => $request->user()
            ->constrainVisibleReports($reports)->whereHas('beneficiaries'))->pluck('id');
        $assigned = fn (Builder $services) => $services->whereIn('servicio_actividad.id', $available);
        $assignments = $this->query($request, $filters)->get();

        return view('servicios.programados', [
            'filters' => $filters,
            'dateBounds' => $dateBounds,
            // DataTable paginates/searches all filtered assignments, not a Laravel page.
            'asignaciones' => $assignments,
            'entregas' => $filters['vista'] === 'resumen' ? collect() : $this->deliveryRows($request, $filters, $assignments->modelKeys()),
            'periodOptions' => ReportPeriod::options($request->user()->constrainVisibleReports(Report::query())
                ->whereHas('serviciosActividad')->whereHas('beneficiaries')),
            // Include inactive assignments because historical deliveries remain valid.
            'proyectos' => Proyecto::whereHas('asignacionesIndicadores.asignacionesActividades.asignacionesServicios', $assigned)->orderBy('codigo')->get(),
            'sectores' => Sector::whereHas('asignacionesProyectos.asignacionesIndicadores.asignacionesActividades.asignacionesServicios', $assigned)->orderBy('name')->get(),
            'indicadores' => Indicador::whereHas('asignacionesProyectos.asignacionesActividades.asignacionesServicios', $assigned)->orderBy('codigo')->get(),
            'actividades' => Actividad::whereHas('asignacionesIndicadores.asignacionesServicios', $assigned)->orderBy('codigo')->get(),
            'servicios' => Servicio::whereHas('asignacionesActividades', $assigned)->orderBy('nombre')->get(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        ReportPeriodDates::validate($filters, $this->dateBounds($request, $filters), ['from' => 'attention', 'to' => 'attention']);
        if ($filters['vista'] !== 'resumen') {
            $rows = $this->deliveryRows($request, $filters, $this->query($request, $filters)->get()->modelKeys());
            $selection = $request->validate([
                'table_selection' => ['nullable', 'boolean'],
                'row_keys_json' => ['nullable', 'string', 'json', 'max:1000000'],
            ]);
            if ($request->boolean('table_selection')) {
                $keys = json_decode($selection['row_keys_json'] ?? '[]', true);
                if (! is_array($keys) || ! array_is_list($keys) || count(array_filter($keys,
                    fn ($key) => ! is_string($key) || ! preg_match('/^[1-9][0-9]*(?::[1-9][0-9]*)?$/D', $key))) > 0) {
                    throw ValidationException::withMessages(['row_keys_json' => 'La selección de entregas no es válida.']);
                }
                // Client IDs never authorize a beneficiary or bypass the filters.
                $rows = $rows->whereIn('key', $keys);
            }

            return app(ProgrammedServicesExcelExport::class)->downloadDeliveries($rows, $filters);
        }
        if ($request->has('assignment_ids_json')) {
            $payload = $request->validate(['assignment_ids_json' => ['required', 'string', 'json', 'max:1000000']]);
            $ids = json_decode($payload['assignment_ids_json'], true);
            if (! is_array($ids) || ! array_is_list($ids)) {
                throw ValidationException::withMessages(['assignment_ids_json' => 'La selección de servicios no es válida.']);
            }
            $request->merge(['assignment_ids' => $ids]);
        }
        $selection = $request->validate([
            'table_selection' => ['nullable', 'boolean'],
            'assignment_ids' => ['nullable', 'array'],
            'assignment_ids.*' => ['integer', 'distinct', 'exists:servicio_actividad,id'],
        ]);
        $query = $this->query($request, $filters);
        if ($request->boolean('table_selection')) {
            // All matching DataTable pages, including an empty search result.
            $query->whereIn('servicio_actividad.id', $selection['assignment_ids'] ?? []);
        }

        return app(ProgrammedServicesExcelExport::class)->download($query, $filters);
    }

    public function dates(Request $request): JsonResponse
    {
        // Ignore existing date inputs: they must not narrow the available range.
        $filters = $request->validate(['reporting_period' => ReportPeriod::rules()]);

        return response()->json($this->dateBounds($request, $filters))
            ->header('Cache-Control', 'private, no-store');
    }

    private function dateBounds(Request $request, array $filters): array
    {
        $reports = $request->user()->constrainVisibleReports(Report::query())
            ->whereHas('serviciosActividad')->reportingPeriod($filters['reporting_period'] ?? []);

        return ReportPeriodDates::forBeneficiaries(Beneficiary::query()
            ->whereIn('beneficiaries.report_id', $reports->select('reports.id')));
    }

    private function validatedFilters(Request $request): array
    {
        $validated = $request->validate([
            'vista' => ['nullable', Rule::in(['resumen', 'beneficiario', 'servicio'])],
            'proyecto_id' => ['nullable', 'integer', 'exists:proyectos,id'],
            'sector_id' => ['nullable', 'integer', 'exists:sectors,id'],
            'indicador_id' => ['nullable', 'integer', 'exists:indicadores,id'],
            'actividad_id' => ['nullable', 'integer', 'exists:actividades,id'],
            'servicio_id' => ['nullable', 'integer', 'exists:servicios,id'],
            'estatus' => ['nullable', Rule::in(['0', '1'])],
            'reporting_period' => ReportPeriod::rules(),
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'to.after_or_equal' => 'La fecha hasta no puede ser anterior a la fecha desde.',
            'from.date_format' => 'Ingrese una fecha de atención desde válida.',
            'to.date_format' => 'Ingrese una fecha de atención hasta válida.',
        ]);
        unset($validated['page']);
        $canViewBeneficiaries = $request->user()->can('solo ver registros') && $request->user()->can('ver detalle de registros');
        $validated['vista'] = ($validated['vista'] ?? null) ?: ($canViewBeneficiaries ? 'beneficiario' : 'resumen');
        if ($validated['vista'] !== 'resumen') {
            abort_unless($canViewBeneficiaries, 403);
        }

        return $validated + ['proyecto_id' => '', 'sector_id' => '', 'indicador_id' => '', 'actividad_id' => '', 'servicio_id' => '', 'estatus' => '', 'reporting_period' => [], 'from' => '', 'to' => ''];
    }

    private function deliveredReports(Request $request, array $filters, ?Builder $query = null): Builder
    {
        return $request->user()->constrainVisibleReports($query ?? Report::query())
            ->whereHas('beneficiaries')
            ->reportingPeriod($filters['reporting_period'])
            ->when(filled($filters['from']), fn (Builder $reports) => $reports->whereDate('reports.report_date', '>=', $filters['from']))
            ->when(filled($filters['to']), fn (Builder $reports) => $reports->whereDate('reports.report_date', '<=', $filters['to']));
    }

    private function deliveryRows(Request $request, array $filters, array $assignmentIds): Collection
    {
        $beneficiaries = Beneficiary::query()->whereHas('report', fn (Builder $reports) => $this
            ->deliveredReports($request, $filters, $reports)->whereHas('serviciosActividad',
                fn (Builder $services) => $services->whereIn('servicio_actividad.id', $assignmentIds)))
            ->with([
                'report.proyecto', 'report.indicadorProyecto.indicador', 'report.indicadorProyecto.asignacionSector.sector',
                'report.actividadIndicador.actividad',
                'report.serviciosActividad' => fn (BelongsToMany $services) => $services->whereIn('servicio_actividad.id', $assignmentIds)->with('servicio'),
            ])->orderBy('beneficiaries.id')->get();

        return $beneficiaries->flatMap(function (Beneficiary $person) use ($filters): array {
            $services = $person->report->serviciosActividad;
            if ($filters['vista'] === 'beneficiario') {
                return [['key' => (string) $person->id, 'beneficiary' => $person, 'services' => $services]];
            }

            return $services->map(fn ($service) => [
                'key' => $person->id.':'.$service->id, 'beneficiary' => $person, 'services' => collect([$service]),
            ])->all();
        });
    }

    private function query(Request $request, array $filters): Builder
    {
        $reports = fn (Builder $query) => $this->deliveredReports($request, $filters, $query);
        // Count actual beneficiary rows, not the potentially stale total_beneficiaries.
        // The pivot primary key prevents multiplying a beneficiary within a service.
        $people = Beneficiary::query()->selectRaw('COUNT(*)')
            ->join('report_servicio_actividad', 'report_servicio_actividad.report_id', '=', 'beneficiaries.report_id')
            ->whereColumn('report_servicio_actividad.servicio_actividad_id', 'servicio_actividad.id')
            ->whereIn('beneficiaries.report_id', $this->deliveredReports($request, $filters)->select('reports.id'));

        return ServicioActividad::query()
            ->select('servicio_actividad.*')
            ->selectSub($people, 'beneficiaries_count')
            ->with([
                'servicio', 'actividadIndicador.actividad', 'actividadIndicador.indicadorProyecto.proyecto',
                'actividadIndicador.indicadorProyecto.indicador', 'actividadIndicador.indicadorProyecto.asignacionSector.sector',
            ])
            ->whereHas('reports', $reports)
            ->withCount(['reports' => $reports])
            ->withMin(['reports' => $reports], 'report_date')
            ->withMax(['reports' => $reports], 'report_date')
            ->when(filled($filters['servicio_id']), fn (Builder $query) => $query->where('servicio_id', $filters['servicio_id']))
            ->when(filled($filters['estatus']), fn (Builder $query) => $query->where('servicio_actividad.estatus', $filters['estatus']))
            ->whereHas('actividadIndicador', function (Builder $activities) use ($filters): void {
                $activities->when(filled($filters['actividad_id']), fn (Builder $query) => $query->where('actividad_id', $filters['actividad_id']))
                    ->whereHas('indicadorProyecto', function (Builder $indicators) use ($filters): void {
                        $indicators
                            ->when(filled($filters['proyecto_id']), fn (Builder $query) => $query->where('proyecto_id', $filters['proyecto_id']))
                            ->when(filled($filters['indicador_id']), fn (Builder $query) => $query->where('indicador_id', $filters['indicador_id']))
                            ->when(filled($filters['sector_id']), fn (Builder $query) => $query->whereHas('asignacionSector',
                                fn (Builder $sectors) => $sectors->where('sector_id', $filters['sector_id'])));
                    });
            })
            ->orderBy(Servicio::select('nombre')->whereColumn('servicios.id', 'servicio_actividad.servicio_id'))
            ->orderBy('servicio_actividad.id');
    }
}
