<?php

namespace App\Http\Requests;

use App\Models\Report;

class UpdateReportRequest extends StoreReportRequest
{
    public function authorize(): bool
    {
        $report = $this->route('report');

        return $this->user() !== null
            && $report instanceof Report
            && $report->user_id === $this->user()->id;
    }

    public function rules(): array
    {
        $rules = parent::rules();

        foreach (array_keys($rules) as $field) {
            if ($field === 'beneficiaries' || str_starts_with($field, 'beneficiaries.')) {
                unset($rules[$field]);
            }
        }

        return $rules;
    }
}
