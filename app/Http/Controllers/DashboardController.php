<?php

namespace App\Http\Controllers;

use App\Models\Beneficiary;
use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $reports = $this->visibleReports($request);
        $visibleBeneficiaries = Beneficiary::query()->whereHas(
            'report',
            fn (Builder $reports) => $request->user()->constrainVisibleReports($reports),
        );
        $demographicCounts = (clone $visibleBeneficiaries)
            ->selectRaw("SUM(CASE WHEN age < 18 AND sex = 'Hombre' THEN 1 ELSE 0 END) as boys")
            ->selectRaw("SUM(CASE WHEN age < 18 AND sex = 'Mujer' THEN 1 ELSE 0 END) as girls")
            ->selectRaw("SUM(CASE WHEN age >= 18 AND sex = 'Hombre' THEN 1 ELSE 0 END) as men")
            ->selectRaw("SUM(CASE WHEN age >= 18 AND sex = 'Mujer' THEN 1 ELSE 0 END) as women")
            ->selectRaw("SUM(CASE WHEN disability IS NOT NULL AND disability <> '' AND disability <> 'Ninguna' THEN 1 ELSE 0 END) as people_with_disabilities")
            ->selectRaw("SUM(CASE WHEN pregnant_lactating = 'Sí' THEN 1 ELSE 0 END) as pregnant_or_lactating")
            ->toBase()
            ->first();
        $activityChart = (clone $visibleBeneficiaries)
            ->join('reports as dashboard_reports', 'beneficiaries.report_id', '=', 'dashboard_reports.id')
            ->leftJoin('proyectos as dashboard_projects', 'dashboard_reports.proyecto_id', '=', 'dashboard_projects.id')
            ->leftJoin('indicador_proyecto as dashboard_assignments', 'dashboard_reports.indicador_proyecto_id', '=', 'dashboard_assignments.id')
            ->leftJoin('indicadores as dashboard_indicators', 'dashboard_assignments.indicador_id', '=', 'dashboard_indicators.id')
            ->leftJoin('sectors as dashboard_sectors', 'dashboard_reports.sector_id', '=', 'dashboard_sectors.id')
            ->leftJoin('activities as dashboard_activities', 'dashboard_reports.activity_id', '=', 'dashboard_activities.id')
            ->select([
                DB::raw("COALESCE(dashboard_projects.codigo, dashboard_sectors.name, 'Sin proyecto') as sector"),
                DB::raw("COALESCE(dashboard_indicators.descripcion, dashboard_activities.title, 'Sin indicador') as activity"),
                DB::raw('COUNT(beneficiaries.id) as beneficiary_count'),
            ])
            ->groupByRaw("COALESCE(dashboard_projects.codigo, dashboard_sectors.name, 'Sin proyecto'), COALESCE(dashboard_indicators.descripcion, dashboard_activities.title, 'Sin indicador')")
            ->orderByDesc('beneficiary_count')->limit(15)->toBase()->get();
        $placeChart = (clone $visibleBeneficiaries)
            ->join('reports as dashboard_reports', 'beneficiaries.report_id', '=', 'dashboard_reports.id')
            ->select(['dashboard_reports.place_name as place', DB::raw('COUNT(beneficiaries.id) as beneficiary_count')])
            ->groupBy('dashboard_reports.place_name')
            ->orderByDesc('beneficiary_count')->limit(12)->toBase()->get();
        $attendanceByDate = (clone $visibleBeneficiaries)
            ->join('reports as attendance_reports', 'beneficiaries.report_id', '=', 'attendance_reports.id')
            ->whereNotNull('attendance_reports.report_date')
            ->selectRaw('attendance_reports.report_date as attention_date')
            ->selectRaw("SUM(CASE WHEN beneficiaries.sex = 'Hombre' THEN 1 ELSE 0 END) as men")
            ->selectRaw("SUM(CASE WHEN beneficiaries.sex = 'Mujer' THEN 1 ELSE 0 END) as women")
            ->selectRaw('COUNT(beneficiaries.id) as total')
            ->groupBy('attendance_reports.report_date')
            ->orderBy('attendance_reports.report_date')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->attention_date,
                'men' => (int) $row->men,
                'women' => (int) $row->women,
                'total' => (int) $row->total,
            ])
            ->values();

        return view('dashboard', [
            'isCoordinator' => $request->user()->isCoordinator(),
            'reportCount' => (clone $reports)->count(),
            'beneficiaryTotal' => (int) ((clone $reports)->sum('total_beneficiaries') ?? 0),
            'demographicCounts' => [
                'boys' => (int) ($demographicCounts->boys ?? 0),
                'girls' => (int) ($demographicCounts->girls ?? 0),
                'men' => (int) ($demographicCounts->men ?? 0),
                'women' => (int) ($demographicCounts->women ?? 0),
                'people_with_disabilities' => (int) ($demographicCounts->people_with_disabilities ?? 0),
                'pregnant_or_lactating' => (int) ($demographicCounts->pregnant_or_lactating ?? 0),
            ],
            'demographicChartValues' => [
                (int) ($demographicCounts->boys ?? 0),
                (int) ($demographicCounts->girls ?? 0),
                (int) ($demographicCounts->men ?? 0),
                (int) ($demographicCounts->women ?? 0),
            ],
            'reportedBeneficiaryCount' => (clone $visibleBeneficiaries)->whereNotNull('reported_at')->count(),
            'unreportedBeneficiaryCount' => (clone $visibleBeneficiaries)->whereNull('reported_at')->count(),
            'activityChart' => $activityChart,
            'placeChart' => $placeChart,
            'attendanceByDate' => $attendanceByDate,
            'recentReports' => $reports->with(['state', 'municipality', 'sector', 'activity', 'proyecto', 'indicadorProyecto.indicador'])
                ->latest('report_date')->latest('id')->limit(6)->get(),
        ]);
    }

    private function visibleReports(Request $request): Builder
    {
        $query = Report::query();
        $request->user()->constrainVisibleReports($query);

        return $query;
    }
}
