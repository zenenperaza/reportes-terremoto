@php
    $isRequest = $feedbackValue === 'request';
@endphp
<fieldset class="buzon-feedback-options" data-buzon-feedback>
    <legend>La retroalimentación se refiere a *</legend>
    <div class="buzon-feedback-choices">
        @foreach($schema::FEEDBACK_TYPES as $key => $label)
            <label class="buzon-feedback-choice"><input type="radio" name="feedback_type" value="{{ $key }}" @checked($feedbackValue === $key) required><span>{{ $label }}</span></label>
        @endforeach
    </div>
</fieldset>
<div class="buzon-contact-fields" data-buzon-contact>
    <label><span data-buzon-contact-label="Nombre">Nombre{{ $isRequest ? ' *' : ' (opcional)' }}</span><input type="text" name="full_name" value="{{ $contactName }}" maxlength="150" autocomplete="off" @required($isRequest)></label>
    <label><span data-buzon-contact-label="Teléfono">Teléfono{{ $isRequest ? ' *' : ' (opcional)' }}</span><input type="tel" name="phone" value="{{ $contactPhone }}" maxlength="40" autocomplete="off" @required($isRequest)></label>
    <small>Nombre y teléfono son obligatorios para Solicitudes. En las demás opciones son opcionales.</small>
</div>
