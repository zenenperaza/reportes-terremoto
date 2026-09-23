<?php

namespace App\Http\Requests\Concerns;

use App\Models\Beneficiary;
use App\Models\Report;

trait PreservesReportLocation
{
    protected function preservesExistingLocation(): bool
    {
        $report = $this->route('report');
        $beneficiary = $this->route('beneficiary');
        if (! $report instanceof Report && $beneficiary instanceof Beneficiary) {
            $report = $beneficiary->report;
        }
        if (! $report instanceof Report || $this->boolean('is_community_location')
            || ! $this->user()?->can($beneficiary instanceof Beneficiary ? 'editar beneficiarios' : 'editar registros')
            || ($report->user_id !== $this->user()->id && ! $this->user()->isAdministrator())) {
            return false;
        }

        // An existing snapshot may outlive its catalog entry or project coverage.
        // Only retain it verbatim, on the same project; new locations use normal validation.
        foreach (['proyecto_id', 'state_id', 'municipality_id', 'parish_id', 'place_name', 'installation_type',
            'latitude', 'longitude', 'altitude', 'gps_accuracy'] as $field) {
            $incoming = $this->input($field);
            $current = $report->getAttribute($field);
            if (in_array($field, ['latitude', 'longitude', 'altitude', 'gps_accuracy'], true)
                && is_numeric($incoming) && is_numeric($current)) {
                if ((float) $incoming !== (float) $current) {
                    return false;
                }
            } elseif ((string) ($incoming ?? '') !== (string) ($current ?? '')) {
                return false;
            }
        }

        return true;
    }
}
