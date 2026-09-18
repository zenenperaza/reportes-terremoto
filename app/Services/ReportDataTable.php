<?php

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportDataTable
{
    public static function columns(User $user): array
    {
        $names = ['date', 'reporter', 'created'];
        if ($user->isAdministrator()) {
            array_push($names, 'full_name', 'national_id', 'phone');
        }
        array_push($names, 'age', 'location', 'project', 'indicator', 'activity', 'services', 'service_count', 'recurrent', 'reported');
        if ($user->can('ver detalle de registros')) {
            $names[] = 'actions';
        }
        $classes = [
            'project' => 'report-classification', 'indicator' => 'report-classification report-indicator',
            'activity' => 'report-classification', 'services' => 'report-classification report-services',
            'service_count' => 'report-services-count', 'actions' => 'report-actions no-export',
        ];

        return array_map(fn ($name) => [
            'data' => $name, 'name' => $name, 'className' => $classes[$name] ?? '',
            'orderable' => ! in_array($name, ['services', 'actions']), 'searchable' => $name !== 'actions',
        ], $names);
    }

    public function response(Request $request, Builder $query): JsonResponse|StreamedResponse
    {
        $user = $request->user();
        $total = Beneficiary::whereHas('report', fn (Builder $reports) => $user->constrainVisibleReports($reports))->count();
        // Only many-to-one joins: services must never multiply beneficiary rows.
        $query->select('beneficiaries.*')
            ->join('reports as dt_report', 'dt_report.id', '=', 'beneficiaries.report_id')
            ->leftJoin('proyectos as dt_project', 'dt_project.id', '=', 'dt_report.proyecto_id')
            ->leftJoin('indicador_proyecto as dt_ip', 'dt_ip.id', '=', 'dt_report.indicador_proyecto_id')
            ->leftJoin('indicadores as dt_indicator', 'dt_indicator.id', '=', 'dt_ip.indicador_id')
            ->leftJoin('actividad_indicador as dt_ai', 'dt_ai.id', '=', 'dt_report.actividad_indicador_id')
            ->leftJoin('actividades as dt_activity', 'dt_activity.id', '=', 'dt_ai.actividad_id')
            ->leftJoin('activities as dt_legacy', 'dt_legacy.id', '=', 'dt_report.activity_id')
            ->leftJoin('states as dt_state', 'dt_state.id', '=', 'dt_report.state_id')
            ->leftJoin('municipalities as dt_municipality', 'dt_municipality.id', '=', 'dt_report.municipality_id')
            ->leftJoin('parishes as dt_parish', 'dt_parish.id', '=', 'dt_report.parish_id');

        $count = DB::table('report_servicio_actividad')->selectRaw('count(*)')
            ->whereColumn('report_servicio_actividad.report_id', 'beneficiaries.report_id');
        $search = trim((string) $request->input('search.value', ''));
        $fields = ['dt_report.reporter_first_name', 'dt_report.reporter_last_name', 'dt_report.organization',
            'dt_report.place_name', 'dt_state.name', 'dt_municipality.name', 'dt_parish.name',
            'dt_project.codigo', 'dt_indicator.codigo', 'dt_indicator.descripcion',
            'dt_activity.codigo', 'dt_activity.descripcion', 'beneficiaries.sex'];
        if ($user->isAdministrator()) {
            array_push($fields, 'beneficiaries.full_name', 'beneficiaries.national_id', 'beneficiaries.phone');
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $search)) {
            $search = substr($search, 6).'-'.substr($search, 3, 2).'-'.substr($search, 0, 2);
        }
        foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $term) {
            $query->where(function (Builder $match) use ($fields, $term, $count): void {
                foreach ($fields as $field) {
                    $match->orWhere($field, 'like', '%'.$term.'%');
                }
                $match->orWhere(function (Builder $legacy) use ($term): void {
                    $legacy->whereNull('dt_indicator.id')->where(fn (Builder $q) => $q
                        ->where('dt_legacy.code', 'like', '%'.$term.'%')->orWhere('dt_legacy.title', 'like', '%'.$term.'%'));
                });
                $match->orWhereHas('report.serviciosActividad.servicio', fn (Builder $q) => $q->where('nombre', 'like', '%'.$term.'%'));
                $match->orWhere('dt_report.report_date', 'like', '%'.$term.'%')
                    ->orWhere('beneficiaries.created_at', 'like', '%'.$term.'%');
                if (ctype_digit($term)) {
                    $match->orWhere('beneficiaries.age', (int) $term)->orWhere($count, '=', (int) $term);
                }
                if (in_array(mb_strtolower($term), ['sí', 'si', 'no'])) {
                    $yes = mb_strtolower($term) !== 'no';
                    $match->orWhere('beneficiaries.is_recurrent', $yes);
                    $yes ? $match->orWhereNotNull('beneficiaries.reported_at') : $match->orWhereNull('beneficiaries.reported_at');
                }
            });
        }
        $filtered = (clone $query)->count();
        $query->selectSub($count, 'selected_service_count');
        $orderFields = [
            'date' => 'dt_report.report_date', 'reporter' => 'dt_report.reporter_first_name',
            'created' => 'beneficiaries.created_at', 'full_name' => 'beneficiaries.full_name',
            'national_id' => 'beneficiaries.national_id', 'phone' => 'beneficiaries.phone',
            'age' => 'beneficiaries.age', 'location' => 'dt_state.name', 'project' => 'dt_project.codigo',
            'indicator' => DB::raw('COALESCE(dt_indicator.codigo, dt_legacy.code)'),
            'activity' => 'dt_activity.codigo', 'service_count' => 'selected_service_count',
            'recurrent' => 'beneficiaries.is_recurrent', 'reported' => 'beneficiaries.reported_at',
        ];
        $columns = self::columns($user);
        foreach ($request->input('order', []) as $order) {
            // Never trust column names, permissions or SQL supplied by the client.
            $column = $columns[(int) $order['column']]['data'] ?? null;
            if (isset($orderFields[$column])) {
                $query->orderBy($orderFields[$column], $order['dir']);
            }
        }
        $query->orderByDesc('beneficiaries.created_at')->orderByDesc('beneficiaries.id');
        $query->with(['report.state', 'report.municipality', 'report.parish', 'report.proyecto',
            'report.activity', 'report.indicadorProyecto.indicador',
            'report.actividadIndicador.actividad', 'report.serviciosActividad.servicio']);

        if ($request->filled('export_type')) {
            // Same filters/order/permissions as the table, without its LIMIT/OFFSET.
            // Hydrate in batches so the server need not load the entire export at once.
            return response()->stream(function () use ($query, $user): void {
                DB::transaction(function () use ($query, $user): void {
                    echo '{"data":[';
                    $first = true;
                    foreach ($query->lazy(500) as $beneficiary) {
                        if (! $first) echo ',';
                        $row = $this->row($beneficiary, $user);
                        unset($row['actions']);
                        echo json_encode($row, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
                        $first = false;
                    }
                    echo ']}';
                });
            }, 200, ['Content-Type' => 'application/json; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
        }

        $length = $request->integer('length', 15);
        $length = $length < 1 ? 100 : min($length, 100);
        $rows = $query->offset($request->integer('start'))->limit($length)
            ->get()->map(fn (Beneficiary $beneficiary) => $this->row($beneficiary, $user));

        return response()->json([
            'draw' => $request->integer('draw'), 'recordsTotal' => $total,
            'recordsFiltered' => $filtered, 'data' => $rows,
        ])->header('Cache-Control', 'private, no-store');
    }

    private function row(Beneficiary $beneficiary, User $user): array
    {
        $report = $beneficiary->report;
        $indicator = $report->indicadorProyecto?->indicador;
        $activity = $report->actividadIndicador?->actividad;
        $row = [
            'date' => e($report->report_date->format('d/m/Y')),
            'reporter' => e($report->reporter_first_name.' '.$report->reporter_last_name).'<br><small>'.e($report->organization).'</small>',
            'created' => e($beneficiary->created_at->format('d/m/Y')).'<br><small>'.e($beneficiary->created_at->format('h:i A')).'</small>',
        ];
        if ($user->isAdministrator()) {
            $row += ['full_name' => e($beneficiary->full_name), 'national_id' => e($beneficiary->national_id ?: 'Sin cédula'), 'phone' => e($beneficiary->phone ?: 'Sin teléfono')];
        }
        $row += [
            'age' => '<strong>'.e($beneficiary->age).' a&ntilde;os</strong><br><small>'.e($beneficiary->sex).'</small>',
            'location' => e($report->state?->name).'<br><small>'.e($report->municipality?->name.', '.$report->parish?->name).'</small><br><small>'.e($report->place_name).'</small>',
            'project' => '<strong>'.e($report->proyecto?->codigo ?? 'Sin proyecto asociado').'</strong>',
            'indicator' => '<strong>'.e($indicator?->codigo ?? $report->activity?->code ?? 'Sin indicador asociado').'</strong><br><small>'.e($indicator?->descripcion ?? $report->activity?->title ?? 'Sin descripción').'</small>',
            'activity' => $activity ? '<strong>'.e($activity->codigo).'</strong><br><small>'.e($activity->descripcion).'</small>' : '<span class="muted">Sin actividad asociada</span>',
            'services' => $report->serviciosActividad->isEmpty() ? '<span class="muted">Sin servicios asociados</span>' : $report->serviciosActividad->map(fn ($service) => '<span class="report-service">'.e($service->servicio?->nombre ?? 'Servicio sin descripción').'</span>')->implode('<br>'),
            'service_count' => (int) $beneficiary->selected_service_count,
            'recurrent' => '<span class="status status-'.($beneficiary->is_recurrent ? 'submitted' : 'reviewed').'">'.($beneficiary->is_recurrent ? 'Sí' : 'No').'</span>',
            'reported' => '<span class="status status-'.($beneficiary->reported_at ? 'reviewed' : 'submitted').'">'.($beneficiary->reported_at ? 'Sí' : 'No').'</span>'.($beneficiary->reported_at ? '<br><small>'.e($beneficiary->reported_at->format('d/m/Y')).'</small>' : ''),
        ];
        if ($user->can('ver detalle de registros')) {
            $row['actions'] = '<a href="'.e(route('reports.show', $report)).'">Ver</a>';
        }

        return $row;
    }
}
