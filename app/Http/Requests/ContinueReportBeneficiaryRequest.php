<?php

namespace App\Http\Requests;

use App\Services\ReportContinuation;
use App\Services\ReportRegistrant;
use Illuminate\Support\Facades\DB;

class ContinueReportBeneficiaryRequest extends StoreBeneficiaryEntryRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        foreach ([1, 2, 3] as $slot) {
            $rules['evidence_'.$slot] = ['prohibited'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        $report = $this->route('report');
        app(ReportContinuation::class)->assertAllowed($this->user(), $report);

        // Only the new person's data comes from the browser. The URL selects
        // the existing list; neither its identity nor its headers can be forged.
        $this->replace(['beneficiary' => $this->input('beneficiary')] + $report->only([
            'organization', 'other_organization', 'state_id', 'municipality_id', 'parish_id',
            'installation_type', 'place_name', 'latitude', 'longitude', 'altitude', 'gps_accuracy',
            'proyecto_id', 'indicador_proyecto_id', 'actividad_indicador_id',
            'activity_details', 'qualitative_notes',
        ]) + [
            'report_id' => $report->id,
            'report_date' => $report->report_date->format('Y-m-d'),
            'sector_proyecto_id' => $report->indicadorProyecto->sector_proyecto_id,
            'servicio_actividad_ids' => $report->serviciosActividad()->pluck('servicio_actividad.id')->all(),
            'associated_indicator_ids' => DB::table('report_indicator_copies')
                ->where('source_report_id', $report->id)->pluck('indicator_assignment_id')->all(),
        ]);

        parent::prepareForValidation();
        // A coordinator continuing a teammate's list retains the original registrant.
        $this->merge(ReportRegistrant::fields($this->user(), $report));
    }
}
