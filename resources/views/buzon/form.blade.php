@extends('layouts.app')
@section('title', ($readOnly ? 'Opinión registrada' : ($entry->exists ? 'Editar opinión' : 'Registrar opinión')).' | Buzón')
@push('styles')<link rel="stylesheet" href="{{ asset('css/buzon.css') }}?v={{ filemtime(public_path('css/buzon.css')) }}">@endpush
@section('content')
@php
    $schema = \App\Models\BuzonEntry::class;
    $backUrl = $canViewList ? route('buzon.lists.show', $listing) : (auth()->user()->can('ver buzon')
        ? route('buzon.index', $selectedProject ? ['project_ids' => [$selectedProject]] : [])
        : route('dashboard'));
    $formValue = function ($field, $fallback) use ($readOnly) {
        $value = $readOnly ? $fallback : old($field, $fallback);
        return is_scalar($value) || $value === null ? $value : '';
    };
    $selectedDisabilities = $readOnly ? ($entry->disabilities ?? []) : old('disabilities', session()->hasOldInput() ? [] : ($entry->disabilities ?? []));
    $selectedDisabilities = is_array($selectedDisabilities) ? $selectedDisabilities : [];
@endphp
<section class="page-heading"><div><p class="eyebrow">{{ $schema::INDICATOR_NAME }}</p><h1>{{ $readOnly ? 'Opinión registrada' : ($entry->exists ? 'Editar opinión' : 'Registrar opinión') }}</h1><p class="muted">Formato de registro de comentarios y quejas · {{ $schema::INDICATOR_CODE }}</p></div>
    <div class="buzon-entry-actions">
        @if($listing && ! $entry->exists)<a class="button button-secondary" href="{{ route('buzon.create', ['proyecto_id' => $selectedProject]) }}">Iniciar nuevo listado</a>@endif
        <a class="button button-secondary" href="{{ $backUrl }}">{{ $canViewList ? 'Volver al listado' : (auth()->user()->can('ver buzon') ? 'Volver al Buzón' : 'Volver al panel') }}</a>
    </div>
</section>
<section class="content-card">
    <p class="buzon-notice">Este formulario pertenece exclusivamente al Buzón. No crea beneficiarios ni registros de otros indicadores. El nombre y el teléfono son opcionales; puede registrar una opinión anónima.</p>
    @if($listing)<p class="buzon-list-context">Listado #{{ $listing->id }} · Creado el {{ $listing->created_at->format('d/m/Y H:i') }}. {{ $entry->exists ? 'Esta opinión pertenece al listado.' : 'Cada opinión guardada se agregará a este mismo listado.' }}</p>@endif
    <form method="post" action="{{ $entry->exists ? route('buzon.update', $entry) : route('buzon.store') }}" autocomplete="off" data-buzon-entry>
        @csrf
        @if($listing && ! $entry->exists)<input type="hidden" name="buzon_list_id" value="{{ $listing->id }}">@endif
        @if($entry->exists) @method('PUT')<input type="hidden" name="version" value="{{ $formValue('version', $entry->version) }}">@endif
        <fieldset @disabled($readOnly) class="buzon-fieldset">
            @unless($readOnly)
                <section class="buzon-data-voice" aria-labelledby="buzon-voice-title" data-buzon-dictation>
                    <div class="buzon-voice-heading">
                        <div><h2 id="buzon-voice-title">Dictado rápido</h2><p>Diga los datos de la persona en una sola frase. Podrá revisarlos antes de guardar.</p></div>
                        <button type="button" class="button button-secondary" data-buzon-voice-target="datos" aria-controls="buzon-data-transcript" aria-pressed="false" disabled><i class="ri-mic-line" aria-hidden="true"></i> <span data-buzon-voice-label>Dictar datos</span></button>
                    </div>
                    <p class="buzon-voice-example"><strong>Ejemplo:</strong> María González, 34 años, mujer, teléfono 0414 123 4567, lugar Comunidad San José, discapacidad ninguna, afrodescendiente no.</p>
                    <details data-buzon-transcript-panel><summary>Transcripción y ayuda</summary>
                        <label for="buzon-data-transcript">Transcripción<textarea id="buzon-data-transcript" data-buzon-data-transcript rows="3" maxlength="4000" placeholder="Nombre María González, edad 34 años, género femenino…"></textarea></label>
                        <div class="buzon-voice-actions"><button type="button" class="button button-secondary" data-buzon-voice-apply>Completar campos</button><button type="button" class="button button-ghost" data-buzon-voice-clear>Limpiar dictado</button></div>
                        <p>Proyecto y fecha se seleccionan manualmente y no se modifican por dictado. También puede decir «opinión muy buena» y «tipo sugerencia». Los comentarios y propuestas de mejora se dictan con sus botones independientes más abajo.</p>
                        <small>SIA no almacena audio. El navegador puede procesarlo mediante un servicio externo de reconocimiento de voz.</small>
                    </details>
                    <p data-buzon-voice-status role="status" aria-live="polite" hidden></p>
                    <p data-buzon-voice-preview class="buzon-voice-preview" hidden></p>
                </section>
            @endunless
            <div class="form-grid two-cols">
                <label>Proyecto *<select name="proyecto_id" required @disabled($listing !== null)><option value="">Seleccione un proyecto</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((string) $selectedProject === (string) $project->id)>{{ $project->codigo }} — {{ $project->nombre_alias ?: $project->descripcion }}</option>@endforeach</select>
                    @if($listing)<input type="hidden" name="proyecto_id" value="{{ $listing->proyecto_id }}"><small>El proyecto corresponde al listado. Para otro proyecto, inicie un nuevo listado.</small>@endif
                </label>
                <label>Fecha de la opinión *<input type="date" name="opinion_date" value="{{ $formValue('opinion_date', $entry->opinion_date?->format('Y-m-d')) }}" max="{{ today()->toDateString() }}" required></label>
                <label class="span-two">Lugar de la queja o sugerencia *<input type="text" name="place_name" value="{{ $formValue('place_name', $entry->place_name) }}" maxlength="200" required><small>Indique el centro, comunidad o lugar al que se refiere esta opinión.</small></label>
                <label>Nombre (opcional)<input type="text" name="full_name" value="{{ $formValue('full_name', $entry->full_name) }}" maxlength="150"></label>
                <label>Teléfono (opcional)<input type="tel" name="phone" value="{{ $formValue('phone', $entry->phone) }}" maxlength="40"></label>
                <label>Edad (opcional)<input type="number" name="age" value="{{ $formValue('age', $entry->age) }}" min="0" max="120" step="1"></label>
                <label>Género (opcional)<select name="gender"><option value="">No indicado</option>@foreach($schema::GENDERS as $value => $label)<option value="{{ $value }}" @selected($formValue('gender', $entry->gender) === $value)>{{ $label }}</option>@endforeach</select></label>
                <fieldset class="buzon-options"><legend>Discapacidad (opcional)</legend>@foreach($schema::DISABILITIES as $value => $label)<label><input type="checkbox" name="disabilities[]" value="{{ $value }}" @checked(in_array($value, $selectedDisabilities, true))> {{ $label }}</label>@endforeach<small>No combine «Ninguna» con otra discapacidad.</small></fieldset>
                <label>Afrodescendiente (opcional)<select name="afro_descendant"><option value="">No indicado</option><option value="1" @selected((string) $formValue('afro_descendant', $entry->afro_descendant === null ? '' : (int) $entry->afro_descendant) === '1')>Sí</option><option value="0" @selected((string) $formValue('afro_descendant', $entry->afro_descendant === null ? '' : (int) $entry->afro_descendant) === '0')>No</option></select></label>
                <fieldset class="span-two buzon-options buzon-opinions"><legend>¿Cómo se sintió con la actividad? *</legend>@foreach($schema::OPINIONS as $value => $label)<label><input type="radio" name="opinion" value="{{ $value }}" @checked($formValue('opinion', $entry->opinion) === $value) required> {{ $label }}</label>@endforeach</fieldset>
                @foreach(['comment' => ['Comentario, sugerencia o queja', 'Dictar comentario', 5], 'improvement' => ['¿Qué podemos hacer para mejorar?', 'Dictar mejora', 4]] as $field => [$label, $buttonLabel, $rows])
                    <div class="span-two buzon-dictation-field">
                        <div class="buzon-voice-toolbar">
                            <label for="buzon_{{ $field }}">{{ $label }}</label>
                            @unless($readOnly)<button type="button" class="button button-secondary" data-buzon-voice-target="{{ $field }}" aria-controls="buzon_{{ $field }}" aria-pressed="false" disabled><i class="ri-mic-line" aria-hidden="true"></i> <span data-buzon-voice-label>{{ $buttonLabel }}</span></button>@endunless
                        </div>
                        <textarea id="buzon_{{ $field }}" name="{{ $field }}" rows="{{ $rows }}" maxlength="10000">{{ $formValue($field, $entry->{$field}) }}</textarea>
                    </div>
                @endforeach
                <label class="span-two">La retroalimentación se refiere a *<select name="feedback_type" required><option value="">Seleccione el tipo</option>@foreach($schema::FEEDBACK_TYPES as $value => $label)<option value="{{ $value }}" @selected($formValue('feedback_type', $entry->feedback_type) === $value)>{{ $label }}</option>@endforeach</select><small>Clasificación realizada por el personal de ASONACOP.</small></label>
            </div>
        </fieldset>
        @unless($readOnly)<div class="form-actions"><a class="button button-secondary" href="{{ $backUrl }}">Cancelar</a><button class="button button-primary" type="submit">{{ $entry->exists ? 'Guardar cambios' : 'Guardar formulario del Buzón' }}</button></div>@endunless
    </form>
    @if($readOnly)
        <p class="muted">Registrado por {{ $entry->creator?->name ?: 'Usuario no disponible' }} · {{ $entry->created_at?->format('d/m/Y H:i') }}</p>
        <div class="buzon-entry-actions">
            @if($canManageEntry)
                @can('editar buzon')<a class="button button-primary" href="{{ route('buzon.edit', $entry) }}">Editar formulario</a>@endcan
            @endif
            @include('buzon.partials.delete-button')
        </div>
    @endif
</section>
@if(! $entry->exists)
    @if($canViewList)
        @include('buzon.lists.partials.entries')
    @else
        <section class="content-card"><h2>Opiniones guardadas en este listado</h2>
            <p class="muted">{{ auth()->user()->can('ver buzon') ? 'Al guardar la primera opinión se creará un listado y sus registros aparecerán aquí. Podrá retomarlo desde Consultar listados.' : 'Puede seguir guardando opiniones. Para consultar el listado y las opiniones anteriores necesita el permiso «ver buzon».' }}</p>
        </section>
    @endif
@endif
@endsection
@push('scripts')<script src="{{ asset('js/buzon.js') }}?v={{ filemtime(public_path('js/buzon.js')) }}"></script>@endpush
@unless($readOnly) @push('scripts')<script src="{{ asset('js/buzon-voice-data.js') }}?v={{ filemtime(public_path('js/buzon-voice-data.js')) }}"></script><script src="{{ asset('js/buzon-dictation.js') }}?v={{ filemtime(public_path('js/buzon-dictation.js')) }}"></script>@endpush @endunless
