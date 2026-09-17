@props(['name', 'label', 'type' => 'text', 'value' => null, 'options' => [], 'required' => false, 'readOnly' => false, 'hint' => null, 'wide' => false, 'max' => null])
@php($current = $readOnly ? $value : old($name, $value))
<div class="{{ $wide ? 'col-12' : 'col-md-6' }}">
    @if($readOnly)
        <div class="case-field-label">{{ $label }}</div>
        <div class="case-field-value">{{ $type === 'select' ? ($options[$current ?? ''] ?? 'Sin registrar') : (($current !== null && $current !== '') ? $current : 'Sin registrar') }}</div>
    @else
        <label class="form-label" for="case-{{ $name }}">{{ $label }}@if($required)<span aria-hidden="true"> *</span>@endif</label>
        @if($type === 'textarea')
            <textarea class="form-control @error($name) is-invalid @enderror" id="case-{{ $name }}" name="{{ $name }}" rows="4" maxlength="10000" @required($required) @if($hint) aria-describedby="hint-{{ $name }}" @endif>{{ $current }}</textarea>
        @elseif($type === 'select')
            <select class="form-select @error($name) is-invalid @enderror" id="case-{{ $name }}" name="{{ $name }}" @required($required) @if($hint) aria-describedby="hint-{{ $name }}" @endif>
                <option value="">Seleccione</option>
                @foreach($options as $key => $text)<option value="{{ $key }}" @selected((string)$current === (string)$key)>{{ $text }}</option>@endforeach
            </select>
        @else
            <input class="form-control @error($name) is-invalid @enderror" id="case-{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}" @required($required) @if($type === 'number') min="0" max="130" step="1" @elseif($type === 'date') max="{{ now()->format('Y-m-d') }}" @else maxlength="{{ $max ?? 200 }}" @endif @if($hint) aria-describedby="hint-{{ $name }}" @endif>
        @endif
        @if($hint)<small class="form-text" id="hint-{{ $name }}">{{ $hint }}</small>@endif
        @error($name)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @endif
</div>
