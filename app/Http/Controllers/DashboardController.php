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
            ->join('sectors as dashboard_sectors', 'dashboard_reports.sector_id', '=', 'dashboard_sectors.id')
            ->join('activities as dashboard_activities', 'dashboard_reports.activity_id', '=', 'dashboard_activities.id')
            ->select([
                'dashboard_sectors.name as sector',
                'dashboard_activities.title as activity',
                DB::raw('COUNT(beneficiaries.id) as beneficiary_count'),
            ])
            ->groupBy('dashboard_sectors.id', 'dashboard_sectors.name', 'dashboard_activities.id', 'dashboard_activities.title')
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
            'recentReports' => $reports->with(['state', 'municipality', 'sector'])
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
