<?php

namespace App\Http\Requests;

use App\Models\Beneficiary;

class UpdateBeneficiaryAttentionRequest extends StoreBeneficiaryEntryRequest
{
    public function rules(): array
    {
        return parent::rules() + ['source_report_id' => ['nullable', 'integer', 'min:1']];
    }

    public function authorize(): bool
    {
        $beneficiary = $this->route('beneficiary');

        return $this->user() !== null
            && $beneficiary instanceof Beneficiary
            && $this->user()->can('editar beneficiarios')
            && ($beneficiary->report->user_id === $this->user()->id || $this->user()->isAdministrator());
    }

    protected function prepareForValidation(): void
    {
        // Never let the client choose a different parent or change the original registrant.
        $this->merge(['report_id' => $this->route('beneficiary')->report_id]);
        parent::prepareForValidation();
    }
}
