<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $reports = $this->visibleReports($request);

        return view('dashboard', [
            'isCoordinator' => $request->user()->isCoordinator(),
            'reportCount' => (clone $reports)->count(),
            'beneficiaryTotal' => (int) ((clone $reports)->sum('total_beneficiaries') ?? 0),
            'submittedCount' => (clone $reports)->where('status', 'submitted')->count(),
            'reviewedCount' => (clone $reports)->where('status', 'reviewed')->count(),
            'recentReports' => $reports->with(['state', 'municipality', 'sector'])
                ->latest('report_date')->latest('id')->limit(6)->get(),
        ]);
    }

    private function visibleReports(Request $request): Builder
    {
        $query = Report::query();

        if (! $request->user()->isCoordinator()) {
            $query->where('user_id', $request->user()->id);
        }

        return $query;
    }
}
