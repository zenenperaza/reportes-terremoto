<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IndicatorReportController extends GeneralReportController
{
    public function export(Request $request): StreamedResponse
    {
        return $this->exportReport($request, true);
    }

    public function __invoke(Request $request): View
    {
        $viewData = $this->buildViewData($request);
        $viewData['locationsRoute'] = route('indicator-reports.locations');
        $viewData['dateBoundsRoute'] = route('indicator-reports.dates');

        return view('indicator-reports.index', $viewData);
    }
}
