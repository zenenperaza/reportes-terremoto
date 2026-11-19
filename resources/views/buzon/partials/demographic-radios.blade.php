<fieldset class="buzon-feedback-options buzon-demographic-options" data-buzon-demographic="{{ $fieldName }}">
    <legend>{{ $fieldLabel }} (opcional)</legend>
    <div class="buzon-feedback-choices">
        @foreach($fieldOptions as $key => $label)
            <label class="buzon-feedback-choice"><input type="radio" name="{{ $fieldName }}" value="{{ $key }}" @checked((string) $fieldValue === (string) $key)><span>{{ $label }}</span></label>
        @endforeach
    </div>
</fieldset>
