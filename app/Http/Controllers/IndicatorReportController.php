<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class IndicatorReportController extends GeneralReportController
{
    public function __invoke(Request $request): View
    {
        $viewData = $this->buildViewData($request);
        $viewData['locationsRoute'] = route('indicator-reports.locations');

        return view('indicator-reports.index', $viewData);
    }
}
