<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Preserve only the identities needed to explain a deletion, in its transaction. */
class ReportDeletionAudit
{
    public const REQUEST_ATTRIBUTE = 'report_deletion_audit_id';

    public function reason(Request $request): string
    {
        $reason = $request->input('deletion_reason');
        $request->merge(['deletion_reason' => is_string($reason) ? trim($reason) : $reason]);

        return $request->validate([
            'deletion_reason' => ['required', 'string', 'max:1000'],
        ], [
            'deletion_reason.required' => 'Indique el motivo de eliminación.',
            'deletion_reason.string' => 'El motivo de eliminación debe ser un texto.',
            'deletion_reason.max' => 'El motivo de eliminación no puede superar los 1000 caracteres.',
        ])['deletion_reason'];
    }

    public function record(Request $request, Report $report, Collection $people, string $reason, bool $reportDeleted): void
    {
        if ($report->getConnection()->transactionLevel() === 0) {
            throw new \LogicException('La eliminación y su bitácora requieren una transacción.');
        }

        $user = $request->user();
        $log = AuditLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'action' => $request->routeIs('reports.destroy') ? 'Eliminó un registro y sus beneficiarios' : 'Eliminó un beneficiario',
            'method' => 'DELETE',
            'route_name' => $request->route()?->getName(),
            'path' => '/'.ltrim($request->path(), '/'),
            'status_code' => $request->routeIs('reports.destroy') ? 302 : 200,
            'duration_ms' => max(0, (int) round((microtime(true) - $request->server('REQUEST_TIME_FLOAT', microtime(true))) * 1000)),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
            'request_payload' => [
                'deletion' => [
                    'reason' => $reason,
                    'report_id' => $report->id,
                    'project_id' => $report->proyecto_id,
                    'project_code' => $report->proyecto?->codigo,
                    'reporting_period' => $report->reporting_period,
                    'report_deleted' => $reportDeleted,
                    'beneficiaries' => $people->map(fn ($person) => [
                        'id' => $person->id,
                        'full_name' => $person->full_name,
                        'national_id' => $person->national_id,
                    ])->values()->all(),
                ],
            ],
        ]);

        // The normal request logger must not duplicate this committed deletion.
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $log->id);
    }
}
