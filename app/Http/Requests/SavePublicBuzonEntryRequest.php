<?php

namespace App\Http\Requests;

use App\Models\BuzonEntry;
use Illuminate\Validation\Rule;

class SavePublicBuzonEntryRequest extends SaveBuzonEntryRequest
{
    protected $errorBag = 'publicBuzon';

    protected function getRedirectUrl(): string
    {
        return \App\Support\PublicBuzonLink::url($this->route('proyecto'), $this->route('recipient'));
    }

    public function authorize(): bool
    {
        // PublicBuzonAccess checks the signed URL, recipient and project on GET and POST.
        return true;
    }

    public function rules(): array
    {
        return [
            'submission_token' => ['required', 'string', 'regex:/\A[a-zA-Z0-9]{64}\z/'],
            'opinion_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'place_name' => ['required', 'string', 'max:200'],
            'full_name' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'age' => ['nullable', 'integer', 'between:0,120'],
            'gender' => ['nullable', Rule::in(array_keys(BuzonEntry::GENDERS))],
            'disabilities' => ['nullable', 'array', 'max:4'],
            'disabilities.*' => ['required', 'distinct', Rule::in(array_keys(BuzonEntry::DISABILITIES))],
            'afro_descendant' => ['nullable', 'boolean'],
            'opinion' => ['required', Rule::in(array_keys(BuzonEntry::OPINIONS))],
            'feedback_type' => ['required', Rule::in(array_keys(BuzonEntry::FEEDBACK_TYPES))],
            'comment' => ['nullable', 'required_without:improvement', 'string', 'max:10000'],
            'improvement' => ['nullable', 'string', 'max:10000'],
            'website' => ['nullable', 'string', 'max:0'],
            'proyecto_id' => ['prohibited'], 'created_by' => ['prohibited'], 'buzon_list_id' => ['prohibited'],
            'public_recipient_id' => ['prohibited'], 'submitted_via_public_link' => ['prohibited'], 'version' => ['prohibited'],
            'public_submission_key' => ['prohibited'],
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'comment.required_without' => 'Escriba su comentario, sugerencia, queja o propuesta de mejora.',
            'submission_token.required' => 'El formulario se actualizó. Recargue la página antes de enviar su opinión.',
            'submission_token.string' => 'Recargue la página para obtener un formulario válido.',
            'submission_token.regex' => 'Recargue la página para obtener un formulario válido.',
        ];
    }
}
