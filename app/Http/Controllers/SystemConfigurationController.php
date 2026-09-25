<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SystemConfigurationController extends Controller
{
    private const MONTHS = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public function index(): View
    {
        $period = SystemSetting::currentPeriod();

        return view('system-configuration.index', [
            'period' => $period,
            'months' => self::MONTHS,
            'years' => $this->years($period['year']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', Rule::in($this->years(SystemSetting::currentPeriod()['year']))],
        ], [], ['period_month' => 'mes del período', 'period_year' => 'año del período']);

        // One value keeps month and year together; unrelated settings are never modified.
        SystemSetting::query()->updateOrCreate(
            ['key' => SystemSetting::CURRENT_PERIOD],
            ['value' => sprintf('%04d-%02d', $data['period_year'], $data['period_month']), 'updated_by' => $request->user()->id]
        );

        return redirect()->route('system-configuration.index')->with('success', 'Configuraciones guardadas. Período actual: '.self::MONTHS[(int) $data['period_month']].' '.$data['period_year'].'.');
    }

    private function years(int $savedYear): array
    {
        return range(2021, max(today()->year + 5, $savedYear));
    }
}
