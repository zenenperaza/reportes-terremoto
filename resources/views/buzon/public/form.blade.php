<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer">
    <title>Buzón · {{ $project->codigo }} | ASONACOP</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/buzon-public.css') }}?v={{ filemtime(public_path('css/buzon-public.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/buzon-feedback.css') }}?v={{ filemtime(public_path('css/buzon-feedback.css')) }}">
</head>
<body>
<main class="public-buzon">
    <header><img src="{{ asset('icons/asonacop-app.png') }}" width="72" height="72" alt="ASONACOP"><div><p class="eyebrow">Opiniones, sugerencias y quejas</p><h1>Buzón</h1><p><strong>{{ $project->codigo }}</strong>@if($project->nombre_alias) — {{ $project->nombre_alias }}@endif</p></div></header>
    <section class="card">
        <p class="notice">Puede enviar su opinión sin iniciar sesión. Las sugerencias, comentarios y quejas pueden ser anónimos; las Solicitudes requieren nombre y teléfono. No incluya cédulas ni datos sensibles innecesarios. Esta información pertenece al Buzón y no registra beneficiarios.</p>
        @if(session('public_buzon_error'))<p class="error" role="alert">{{ session('public_buzon_error') }}</p>@endif
        @if($errors->publicBuzon->any())<div class="error" role="alert"><strong>Revise los datos del formulario.</strong><ul>@foreach($errors->publicBuzon->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        @php
            $schema = \App\Models\BuzonEntry::class;
            $value = static function ($field, $default = '') use ($errors) {
                $result = $errors->publicBuzon->any() ? old($field, $default) : $default;
                return is_scalar($result) || $result === null ? $result : '';
            };
            $disabilities = $errors->publicBuzon->any() ? old('disabilities', []) : [];
            $disabilities = is_array($disabilities) ? $disabilities : [];
            $submissionToken = $errors->publicBuzon->any() ? old('submission_token') : null;
            if (! is_string($submissionToken) || preg_match('/\A[a-zA-Z0-9]{64}\z/', $submissionToken) !== 1) {
                $submissionToken = \Illuminate\Support\Str::random(64);
            }
        @endphp
        <form method="post" action="{{ $publicUrl }}" autocomplete="off" data-public-buzon-form>
            @csrf
            <input type="hidden" name="submission_token" value="{{ $submissionToken }}">
            <div class="honeypot" aria-hidden="true"><label>Deje este campo vacío<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            @include('buzon.partials.demographic-radios', ['fieldName' => 'age_group', 'fieldLabel' => 'Grupo de edad', 'fieldOptions' => ['' => 'No indicado'] + $schema::AGE_GROUPS, 'fieldValue' => $value('age_group')])
            @include('buzon.partials.feedback-type', ['feedbackValue' => $value('feedback_type'), 'contactName' => $value('full_name'), 'contactPhone' => $value('phone'), 'contactReadOnly' => false])
            <div class="grid">
                <label>Fecha de la opinión *<input type="date" name="opinion_date" value="{{ $value('opinion_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></label>
                <label>Lugar de la queja o sugerencia *<input name="place_name" maxlength="200" value="{{ $value('place_name') }}" required></label>
                <label>Edad (opcional)<input type="number" name="age" min="0" max="120" step="1" value="{{ $value('age') }}"></label>
                @include('buzon.partials.demographic-radios', ['fieldName' => 'gender', 'fieldLabel' => 'Género', 'fieldOptions' => ['' => 'No indicado'] + $schema::GENDERS, 'fieldValue' => $value('gender')])
                <fieldset><legend>Discapacidad (opcional)</legend>@foreach($schema::DISABILITIES as $key => $label)<label class="choice"><input type="checkbox" name="disabilities[]" value="{{ $key }}" @checked(in_array($key, $disabilities, true))> {{ $label }}</label>@endforeach<small>No combine Ninguna con otra discapacidad.</small></fieldset>
                @include('buzon.partials.demographic-radios', ['fieldName' => 'afro_descendant', 'fieldLabel' => 'Afrodescendiente', 'fieldOptions' => ['' => 'No indicado', 1 => 'Sí', 0 => 'No'], 'fieldValue' => $value('afro_descendant')])
                <fieldset class="full"><legend>¿Cómo se sintió con la actividad? *</legend>@foreach($schema::OPINIONS as $key => $label)<label class="choice"><input type="radio" name="opinion" value="{{ $key }}" @checked($value('opinion') === $key) required> {{ $label }}</label>@endforeach</fieldset>
                <label class="full">Comentario, sugerencia, queja o solicitud<textarea name="comment" rows="5" maxlength="10000">{{ $value('comment') }}</textarea></label>
                <label class="full">¿Qué podemos hacer para mejorar?<textarea name="improvement" rows="4" maxlength="10000">{{ $value('improvement') }}</textarea><small>Complete al menos uno de los dos campos de texto.</small></label>
            </div>
            <button type="submit" data-public-buzon-submit>Enviar opinión</button>
            <p data-public-buzon-status role="status" aria-live="polite" hidden></p>
        </form>
    </section>
    <footer>ASONACOP · Buzón de opiniones. Este enlace no permite consultar opiniones anteriores.</footer>
</main>
<script src="{{ asset('js/buzon-feedback.js') }}?v={{ filemtime(public_path('js/buzon-feedback.js')) }}" defer></script>
<script src="{{ asset('js/buzon-public-submit.js') }}?v={{ filemtime(public_path('js/buzon-public-submit.js')) }}" defer></script>
</body>
</html>
