<?php

namespace App\Http\Requests;

use App\Models\CaseRecord;
use App\Models\FamilyRecord;
use App\Models\Proyecto;
use App\Models\User;
use App\Support\CaseFormSchema;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCaseRecordRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // El formulario envía el expediente completo. Un selector dependiente
        // vacío no debe conservar una ubicación anterior al cambiar de estado.
        $this->merge([
            'state_id' => $this->input('state_id'),
            'municipality_id' => $this->input('municipality_id'),
            'parish_id' => $this->input('parish_id'),
        ]);
        if (is_array($this->input('form_data'))) {
            $this->merge(['form_data' => CaseFormSchema::normalize($this->input('form_data'))]);
        }
    }

    public function authorize(): bool
    {
        return $this->isMethod('POST') ? $this->user()->can('create', CaseRecord::class) : $this->user()->can('update', $this->route('caseRecord'));
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:200'],
            'document_type' => ['nullable', 'string', 'max:60'],
            'document_number' => ['nullable', 'string', 'max:80'],
            'birth_date' => ['nullable', 'date', 'before_or_equal:registered_on', 'after_or_equal:1900-01-01'],
            'age_at_registration' => ['nullable', 'integer', 'between:0,130'],
            'sex' => ['required', Rule::in(array_keys(config('case-management.sexes')))],
            'nationality' => ['nullable', 'string', 'max:100'],
            'registered_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:1900-01-01'],
            'case_type' => ['required', Rule::in(array_keys(config('case-management.types')))],
            'proyecto_id' => ['nullable', 'integer', 'exists:proyectos,id'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'municipality_id' => ['nullable', 'integer', Rule::exists('municipalities', 'id')->where('state_id', $this->input('state_id'))],
            'parish_id' => ['nullable', 'integer', Rule::exists('parishes', 'id')->where('municipality_id', $this->input('municipality_id'))],
            'address' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:60'],
            'safe_contact' => ['nullable', 'string', 'max:2000'],
            'family_notes' => ['nullable', 'string', 'max:10000'],
            'support_person' => ['nullable', 'string', 'max:200'],
            'support_relationship' => ['nullable', 'string', 'max:100'],
            'support_phone' => ['nullable', 'string', 'max:60'],
            'consent_status' => ['required', Rule::in(array_keys(config('case-management.consents')))],
            'consent_source' => ['nullable', 'required_if:consent_status,granted', 'string', 'max:200'],
            'consent_date' => ['nullable', 'required_if:consent_status,granted', 'date', 'before_or_equal:today'],
            'consent_notes' => ['nullable', 'string', 'max:10000'],
            'share_services' => ['required', 'boolean'], 'share_reports' => ['required', 'boolean'],
            'risk_level' => ['required', Rule::in(array_keys(config('case-management.risks')))],
            'presenting_needs' => ['nullable', 'string', 'max:10000'],
            'immediate_actions' => ['nullable', 'string', 'max:10000'],
            'version' => [$this->isMethod('POST') ? 'nullable' : 'required', 'integer', 'min:1'],
            'family_record_id' => ['nullable', 'integer', function ($attribute, $value, $fail): void {
                if (! FamilyRecord::visibleTo($this->user())->whereKey($value)->exists()) {
                    $fail('Seleccione una familia a la que tenga acceso.');
                }
            }],
            'status' => ['sometimes', Rule::in(['open', 'closed'])],
        ] + CaseFormSchema::rules($this->user()->can('supervisar casos'));
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $case = $this->route('caseRecord');
            if ($this->has('form_data') && $this->input('form_complete') !== '1') {
                $validator->errors()->add('form_data', 'El formulario llegó incompleto. Revise el límite max_input_vars del servidor antes de guardar.');
            }
            $status = $this->input('status', $case?->status ?? 'open');
            if ($status !== ($case?->status ?? 'open') && ! $this->user()->can('supervisar casos')) {
                $validator->errors()->add('status', 'Solo un supervisor puede cerrar o reabrir el caso.');
            }
            if ($status === 'closed' && ($case?->status !== 'closed' || $this->has('form_data.closure'))) {
                foreach (['reason', 'date'] as $required) {
                    if (! $this->filled('form_data.closure.'.$required)) {
                        $validator->errors()->add('form_data.closure.'.$required, 'Registre el motivo y la fecha de cierre.');
                    }
                }
            }
            if ($this->input('form_data.closure.reason') === 'other' && ! $this->filled('form_data.closure.other')) {
                $validator->errors()->add('form_data.closure.other', 'Especifique el motivo de cierre.');
            }
            foreach (['assessment', 'plan', 'closure'] as $section) {
                if ($this->input('form_data.'.$section.'.approval_status') === 'approved' && (! $this->filled('form_data.'.$section.'.approved_date') || $this->input('form_data.'.$section.'.approved') !== 'yes')) {
                    $validator->errors()->add('form_data.'.$section.'.approved_date', 'Confirme la aprobación y registre su fecha.');
                }
            }
            if ($this->input('case_type') === 'vbg' && ! $this->user()->can('gestionar casos vbg')) {
                $validator->errors()->add('case_type', 'No tiene permiso para gestionar casos de VBG.');
            }
            $assigneeId = $this->input('assigned_to') ?: ($case?->assigned_to ?? $this->user()->id);
            $previousAssignee = $case?->assigned_to ?? $this->user()->id;
            if ((int) $assigneeId !== (int) $previousAssignee && ! $this->user()->can('asignar casos')) {
                $validator->errors()->add('assigned_to', 'No tiene permiso para cambiar el responsable.');
            }
            $assignee = User::find($assigneeId);
            if (! $assignee || ! $assignee->is_active || ! $assignee->can('ver casos') || ($this->input('case_type') === 'vbg' && ! $assignee->can('gestionar casos vbg'))) {
                $validator->errors()->add('assigned_to', 'Seleccione un responsable activo con acceso a este tipo de caso.');
            }
            if ($this->filled('proyecto_id') && (int) $this->input('proyecto_id') !== (int) $case?->proyecto_id) {
                $project = Proyecto::find($this->integer('proyecto_id'));
                if (! $project?->estatus || (! $this->user()->can('supervisar casos') && ! $this->user()->projects()->whereKey($project->id)->exists())) {
                    $validator->errors()->add('proyecto_id', 'Seleccione un proyecto activo que tenga asignado.');
                }
            }
            if ($this->input('consent_status') !== 'granted' && ($this->boolean('share_services') || $this->boolean('share_reports'))) {
                $validator->errors()->add('consent_status', 'Para autorizar el uso compartido debe registrar el consentimiento otorgado.');
            }
        }];
    }

    public function attributes(): array
    {
        return config('case-management.fields');
    }
}
