<?php

namespace App\Support;

use App\Models\SystemSetting;
use App\Models\ReportingPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReportPeriod
{
    public static function current(): string
    {
        $period = SystemSetting::currentPeriod();

        return sprintf('%04d-%02d', $period['year'], $period['month']);
    }

    public static function forNewReport(Request $request): string
    {
        $period = self::current();
        if ($request->exists('period_snapshot') && $request->input('period_snapshot') !== $period) {
            throw ValidationException::withMessages(['period_snapshot' => 'El período configurado cambió. Recargue el formulario y revise el período antes de guardar.']);
        }

        self::assertOpen($period, true);

        return $period;
    }

    public static function isClosed(?string $period): bool
    {
        return $period !== null && ReportingPeriod::where('period', $period)->where('is_closed', true)->exists();
    }

    public static function assertOpen(?string $period, bool $lock = false): void
    {
        if (!$period) return;
        if ($lock) {
            ReportingPeriod::firstOrCreate(['period' => $period]);
        }
        $query = ReportingPeriod::where('period', $period);
        $record = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_if($record?->is_closed, 409, 'El período '.self::label($period).' está cerrado. No se pueden modificar ni eliminar sus registros o beneficiarios.');
    }

    public static function rules(): array
    {
        return ['nullable', 'string', 'regex:/^(?:[0-9]{4}-(?:0[1-9]|1[0-2])|unassigned)$/'];
    }

    public static function label(?string $period): string
    {
        if (!$period || $period === 'unassigned') {
            return 'Sin período asignado';
        }
        $months = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        return ($months[(int) substr($period, 5, 2)] ?? $period).' '.substr($period, 0, 4);
    }

    public static function options(Builder $reports, bool $includeCurrent = false): Collection
    {
        $options = (clone $reports)->reorder()->whereNotNull('reports.reporting_period')->select('reports.reporting_period')
            ->distinct()->orderByDesc('reports.reporting_period')->pluck('reports.reporting_period')
            ->mapWithKeys(fn ($period) => [$period => self::label($period)]);

        if ($includeCurrent) {
            $current = self::current();
            $options->put($current, self::label($current));
        }

        return $options->sortKeysDesc();
    }
}
