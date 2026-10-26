@extends('layouts.app')
@section('title', ($readOnly ? 'Opinión registrada' : ($entry->exists ? 'Editar opinión' : 'Registrar opinión')).' | Buzón')
@push('styles')<link rel="stylesheet" href="{{ asset('css/buzon.css') }}?v={{ filemtime(public_path('css/buzon.css')) }}">@endpush
@section('content')
@php
    $schema = \App\Models\BuzonEntry::class;
    $formValue = function ($field, $fallback) use ($readOnly) {
        $value = $readOnly ? $fallback : old($field, $fallback);
        return is_scalar($value) || $value === null ? $value : '';
    };
    $selectedDisabilities = $readOnly ? ($entry->disabilities ?? []) : old('disabilities', session()->hasOldInput() ? [] : ($entry->disabilities ?? []));
    $selectedDisabilities = is_array($selectedDisabilities) ? $selectedDisabilities : [];
@endphp
<section class="page-heading"><div><p class="eyebrow">{{ $schema::INDICATOR_NAME }}</p><h1>{{ $readOnly ? 'Opinión registrada' : ($entry->exists ? 'Editar opinión' : 'Registrar opinión') }}</h1><p class="muted">Formato de registro de comentarios y quejas · {{ $schema::INDICATOR_CODE }}</p></div>
    <a class="button button-secondary" href="{{ route('buzon.index', $selectedProject ? ['project_ids' => [$selectedProject]] : []) }}">Volver al Buzón</a>
</section>
<section class="content-card">
    <p class="buzon-notice">Este formulario pertenece exclusivamente al Buzón. No crea beneficiarios ni registros de otros indicadores. El nombre y el teléfono son opcionales; puede registrar una opinión anónima.</p>
    <form method="post" action="{{ $entry->exists ? route('buzon.update', $entry) : route('buzon.store') }}" autocomplete="off" data-buzon-entry>
        @csrf
        @if($entry->exists) @method('PUT')<input type="hidden" name="version" value="{{ $formValue('version', $entry->version) }}">@endif
        <fieldset @disabled($readOnly) class="buzon-fieldset">
            <div class="form-grid two-cols">
                <label>Proyecto *<select name="proyecto_id" required><option value="">Seleccione un proyecto</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((string) $selectedProject === (string) $project->id)>{{ $project->codigo }} — {{ $project->nombre_alias ?: $project->descripcion }}</option>@endforeach</select></label>
                <label>Fecha de la opinión *<input type="date" name="opinion_date" value="{{ $formValue('opinion_date', $entry->opinion_date?->format('Y-m-d')) }}" max="{{ today()->toDateString() }}" required></label>
                <label class="span-two">Lugar de la queja o sugerencia *<input type="text" name="place_name" value="{{ $formValue('place_name', $entry->place_name) }}" maxlength="200" required><small>Indique el centro, comunidad o lugar al que se refiere esta opinión.</small></label>
                <label>Nombre (opcional)<input type="text" name="full_name" value="{{ $formValue('full_name', $entry->full_name) }}" maxlength="150"></label>
                <label>Teléfono (opcional)<input type="tel" name="phone" value="{{ $formValue('phone', $entry->phone) }}" maxlength="40"></label>
                <label>Edad (opcional)<input type="number" name="age" value="{{ $formValue('age', $entry->age) }}" min="0" max="120" step="1"></label>
                <label>Género (opcional)<select name="gender"><option value="">No indicado</option>@foreach($schema::GENDERS as $value => $label)<option value="{{ $value }}" @selected($formValue('gender', $entry->gender) === $value)>{{ $label }}</option>@endforeach</select></label>
                <fieldset class="buzon-options"><legend>Discapacidad (opcional)</legend>@foreach($schema::DISABILITIES as $value => $label)<label><input type="checkbox" name="disabilities[]" value="{{ $value }}" @checked(in_array($value, $selectedDisabilities, true))> {{ $label }}</label>@endforeach<small>No combine «Ninguna» con otra discapacidad.</small></fieldset>
                <label>Afrodescendiente (opcional)<select name="afro_descendant"><option value="">No indicado</option><option value="1" @selected((string) $formValue('afro_descendant', $entry->afro_descendant === null ? '' : (int) $entry->afro_descendant) === '1')>Sí</option><option value="0" @selected((string) $formValue('afro_descendant', $entry->afro_descendant === null ? '' : (int) $entry->afro_descendant) === '0')>No</option></select></label>
                <fieldset class="span-two buzon-options buzon-opinions"><legend>¿Cómo se sintió con la actividad? *</legend>@foreach($schema::OPINIONS as $value => $label)<label><input type="radio" name="opinion" value="{{ $value }}" @checked($formValue('opinion', $entry->opinion) === $value) required> {{ $label }}</label>@endforeach</fieldset>
                <label class="span-two">Comentario, sugerencia o queja<textarea name="comment" rows="5" maxlength="10000">{{ $formValue('comment', $entry->comment) }}</textarea></label>
                <label class="span-two">¿Qué podemos hacer para mejorar?<textarea name="improvement" rows="4" maxlength="10000">{{ $formValue('improvement', $entry->improvement) }}</textarea></label>
                <label class="span-two">La retroalimentación se refiere a *<select name="feedback_type" required><option value="">Seleccione el tipo</option>@foreach($schema::FEEDBACK_TYPES as $value => $label)<option value="{{ $value }}" @selected($formValue('feedback_type', $entry->feedback_type) === $value)>{{ $label }}</option>@endforeach</select><small>Clasificación realizada por el personal de ASONACOP.</small></label>
            </div>
        </fieldset>
        @unless($readOnly)<div class="form-actions"><a class="button button-secondary" href="{{ route('buzon.index') }}">Cancelar</a><button class="button button-primary" type="submit">{{ $entry->exists ? 'Guardar cambios' : 'Guardar formulario del Buzón' }}</button></div>@endunless
    </form>
    @if($readOnly)<p class="muted">Registrado por {{ $entry->creator?->name ?: 'Usuario no disponible' }} · {{ $entry->created_at?->format('d/m/Y H:i') }}</p><a class="button button-primary" href="{{ route('buzon.edit', $entry) }}">Editar formulario</a>@endif
</section>
@endsection
@push('scripts')<script src="{{ asset('js/buzon.js') }}?v={{ filemtime(public_path('js/buzon.js')) }}"></script>@endpush
