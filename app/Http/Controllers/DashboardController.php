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

        return view('dashboard', [
            'isCoordinator' => $request->user()->isCoordinator(),
            'reportCount' => (clone $reports)->count(),
            'beneficiaryTotal' => (int) ((clone $reports)->sum('total_beneficiaries') ?? 0),
            'reportedBeneficiaryCount' => (clone $visibleBeneficiaries)->whereNotNull('reported_at')->count(),
            'unreportedBeneficiaryCount' => (clone $visibleBeneficiaries)->whereNull('reported_at')->count(),
            'activityChart' => $activityChart,
            'placeChart' => $placeChart,
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
