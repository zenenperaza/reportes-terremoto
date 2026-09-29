<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Models\Report;
use App\Models\ReportingPeriod;
use App\Support\ReportPeriod;
use Illuminate\Support\Facades\DB;
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
        $current = ReportPeriod::current();
        $saved = ReportingPeriod::all()->keyBy('period');
        $counts = Report::whereNotNull('reporting_period')->selectRaw('reporting_period, COUNT(*) as total')
            ->groupBy('reporting_period')->pluck('total', 'reporting_period');
        $periods = $saved->keys()->merge($counts->keys())->push($current)->unique()->sortDesc()->values()
            ->map(fn ($value) => [
                'value' => $value, 'label' => ReportPeriod::label($value), 'current' => $value === $current,
                'closed' => $saved->get($value)?->is_closed ?? false,
                'closed_at' => $saved->get($value)?->closed_at, 'count' => (int) $counts->get($value, 0),
            ]);

        return view('system-configuration.index', [
            'period' => $period,
            'months' => self::MONTHS,
            'years' => $this->years($period['year']),
            'periods' => $periods,
            'unassignedCount' => Report::whereNull('reporting_period')->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'period_month' => ['required', 'integer', 'between:1,12'],
            'period_year' => ['required', 'integer', Rule::in($this->years(SystemSetting::currentPeriod()['year']))],
        ], [], ['period_month' => 'mes del período', 'period_year' => 'año del período']);

        DB::transaction(function () use ($request, $data): void {
            $next = sprintf('%04d-%02d', $data['period_year'], $data['period_month']);
            ReportingPeriod::firstOrCreate(['period' => ReportPeriod::current()]);
            ReportPeriod::assertOpen($next, true);
            // Retain the old period even when it had no reports.
            SystemSetting::query()->updateOrCreate(
                ['key' => SystemSetting::CURRENT_PERIOD],
                ['value' => $next, 'updated_by' => $request->user()->id]
            );
        });

        return redirect()->route('system-configuration.index')->with('success', 'Configuraciones guardadas. Período actual: '.self::MONTHS[(int) $data['period_month']].' '.$data['period_year'].'.');
    }

    public function updatePeriod(Request $request, string $period): RedirectResponse
    {
        $data = $request->validate(['is_closed' => ['required', 'boolean']]);
        abort_unless($period === ReportPeriod::current() || ReportingPeriod::where('period', $period)->exists()
            || Report::where('reporting_period', $period)->exists(), 404);

        DB::transaction(function () use ($period, $data, $request): void {
            ReportingPeriod::firstOrCreate(['period' => $period]);
            $record = ReportingPeriod::where('period', $period)->lockForUpdate()->firstOrFail();
            $closed = (bool) $data['is_closed'];
            if ($record->is_closed !== $closed) {
                $record->update(['is_closed' => $closed, 'closed_at' => $closed ? now() : null, 'updated_by' => $request->user()->id]);
            }
        });

        return redirect()->route('system-configuration.index')->with('success',
            ReportPeriod::label($period).($data['is_closed'] ? ': período cerrado.' : ': período abierto.'));
    }

    private function years(int $savedYear): array
    {
        return range(2021, max(today()->year + 5, $savedYear));
    }
}
