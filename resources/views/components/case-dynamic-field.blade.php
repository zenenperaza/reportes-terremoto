@props(['path', 'field', 'value' => null, 'readOnly' => false, 'required' => false])
@php
    $locked = $readOnly || (($field['supervisor'] ?? false) && !auth()->user()->can('supervisar casos'));
    $current = $locked ? $value : old($path, $value);
    $type = $field['type']; $options = $field['options'] ?? [];
    $name = preg_replace('/\.([^\.]+)/', '[$1]', $path); $id = 'field-'.str_replace('.', '-', $path);
    if ($type === 'multi') { $current = is_array($current) ? array_filter($current, 'is_scalar') : []; }
    elseif (is_array($current) || is_object($current)) { $current = null; }
@endphp
<div class="{{ in_array($type, ['textarea', 'multi']) ? 'col-12' : 'col-md-6' }}">
    @if($locked)
        <div class="case-field-label">{{ $field['label'] }}</div>
        <div class="case-field-value">@if($type === 'multi'){{ collect($current)->map(fn($key) => $options[$key] ?? $key)->implode(', ') ?: 'Sin registrar' }}@elseif(in_array($type,['select','radio'])){{ $options[$current ?? ''] ?? 'Sin registrar' }}@else{{ filled($current) ? $current : 'Sin registrar' }}@endif</div>
    @else
        @if($type==='radio')<div class="form-label" id="label-{{ $id }}">{{ $field['label'] }}</div>@else<label class="form-label" for="{{ $id }}">{{ $field['label'] }}{{ $required ? ' *' : '' }}</label>@endif
        @if($type === 'textarea')
            <textarea name="{{ $name }}" id="{{ $id }}" rows="4" maxlength="10000" class="form-control @error($path) is-invalid @enderror" @required($required)>{{ $current }}</textarea>
        @elseif($type === 'radio')
            <input type="hidden" name="{{ $name }}" value="">
            <div role="radiogroup" aria-labelledby="label-{{ $id }}" class="@error($path) is-invalid @enderror">@foreach($options as $key=>$label)<div class="form-check form-check-inline"><input class="form-check-input" type="radio" name="{{ $name }}" id="{{ $id }}-{{ $key }}" value="{{ $key }}" @checked((string)$current===(string)$key)><label class="form-check-label" for="{{ $id }}-{{ $key }}">{{ $label }}</label></div>@endforeach</div>
        @elseif($type === 'multi')
            <input type="hidden" name="{{ $name }}" value="">
            <select name="{{ $name }}[]" id="{{ $id }}" multiple size="5" class="form-select @error($path) is-invalid @enderror">@foreach($options as $key => $label)<option value="{{ $key }}" @selected(in_array((string)$key, array_map('strval', $current), true))>{{ $label }}</option>@endforeach</select>
            <small class="form-text">Puede seleccionar varias opciones (Ctrl o ⌘ + clic).</small>
        @elseif($type === 'select')
            <select name="{{ $name }}" id="{{ $id }}" class="form-select @error($path) is-invalid @enderror" @required($required)><option value="">Seleccione</option>@foreach($options as $key => $label)<option value="{{ $key }}" @selected((string)$current === (string)$key)>{{ $label }}</option>@endforeach</select>
        @else
            <input name="{{ $name }}" id="{{ $id }}" type="{{ $type === 'past_date' ? 'date' : $type }}" value="{{ $current }}" class="form-control @error($path) is-invalid @enderror" @required($required) @if($type === 'number') min="0" max="130" step="1" @elseif($type === 'past_date') max="{{ today()->toDateString() }}" @else maxlength="250" @endif>
        @endif
        @error($path)<div class="invalid-feedback">{{ $message }}</div>@enderror
    @endif
</div>
