<?php

namespace App\Services;

use App\Models\Report;
use App\Models\ReportingPeriod;
use App\Models\User;
use App\Support\ReportPeriod;

/** A separate capability: append people without granting permission to edit the group. */
class ReportContinuation
{
    private ?array $closedPeriods = null;

    public function allows(User $user, Report $report): bool
    {
        $this->closedPeriods ??= ReportingPeriod::where('is_closed', true)->pluck('period')->all();

        return $this->authorized($user, $report)
            && $report->status !== 'reviewed'
            && ! in_array($report->reporting_period, $this->closedPeriods, true)
            && $this->projectAvailable($user, $report);
    }

    public function assertAllowed(User $user, Report $report, bool $lock = false): void
    {
        abort_unless($this->authorized($user, $report), 403);
        abort_if($report->status === 'reviewed', 409, 'No se puede continuar un registro revisado.');
        ReportPeriod::assertOpen($report->reporting_period, $lock);
        abort_unless($this->projectAvailable($user, $report), 409,
            'El proyecto o indicador ya no está disponible para continuar este registro.');
    }

    private function authorized(User $user, Report $report): bool
    {
        return $user->can('registrar actividad') && $user->can('ver detalle de registros')
            && $user->canViewReport($report) && $user->canManageGroupReport($report);
    }

    private function projectAvailable(User $user, Report $report): bool
    {
        $report->loadMissing(['proyecto', 'indicadorProyecto']);

        return $report->proyecto?->estatus && $report->indicadorProyecto?->estatus
            && $report->indicadorProyecto->sector_proyecto_id !== null
            && (int) $report->indicadorProyecto->proyecto_id === (int) $report->proyecto_id
            && ($user->isAdministrator() || $user->projects()->whereKey($report->proyecto_id)->exists());
    }
}
