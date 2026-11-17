<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive"><meta name="referrer" content="no-referrer">
    <title>Buzón · {{ $project->codigo }} | ASONACOP</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/buzon-public.css') }}?v={{ filemtime(public_path('css/buzon-public.css')) }}">
</head>
<body>
<main class="public-buzon">
    <header><img src="{{ asset('icons/asonacop-app.png') }}" width="72" height="72" alt="ASONACOP"><div><p class="eyebrow">Opiniones, sugerencias y quejas</p><h1>Buzón</h1><p><strong>{{ $project->codigo }}</strong>@if($project->nombre_alias) — {{ $project->nombre_alias }}@endif</p></div></header>
    <section class="card">
        <p class="notice">Puede enviar su opinión sin iniciar sesión. El nombre y el teléfono son opcionales. No incluya cédulas ni datos sensibles innecesarios. Esta información pertenece al Buzón y no registra beneficiarios.</p>
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
        @endphp
        <form method="post" action="{{ $publicUrl }}" autocomplete="off">
            @csrf
            <div class="honeypot" aria-hidden="true"><label>Deje este campo vacío<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
            <div class="grid">
                <label>Fecha de la opinión *<input type="date" name="opinion_date" value="{{ $value('opinion_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></label>
                <label>Lugar de la queja o sugerencia *<input name="place_name" maxlength="200" value="{{ $value('place_name') }}" required></label>
                <label>Nombre (opcional)<input name="full_name" maxlength="150" value="{{ $value('full_name') }}"></label>
                <label>Teléfono (opcional)<input type="tel" name="phone" maxlength="40" value="{{ $value('phone') }}"></label>
                <label>Edad (opcional)<input type="number" name="age" min="0" max="120" step="1" value="{{ $value('age') }}"></label>
                <label>Género (opcional)<select name="gender"><option value="">No indicado</option>@foreach($schema::GENDERS as $key => $label)<option value="{{ $key }}" @selected($value('gender') === $key)>{{ $label }}</option>@endforeach</select></label>
                <fieldset><legend>Discapacidad (opcional)</legend>@foreach($schema::DISABILITIES as $key => $label)<label class="choice"><input type="checkbox" name="disabilities[]" value="{{ $key }}" @checked(in_array($key, $disabilities, true))> {{ $label }}</label>@endforeach<small>No combine Ninguna con otra discapacidad.</small></fieldset>
                <label>Afrodescendiente (opcional)<select name="afro_descendant"><option value="">No indicado</option><option value="1" @selected((string) $value('afro_descendant') === '1')>Sí</option><option value="0" @selected((string) $value('afro_descendant') === '0')>No</option></select></label>
                <fieldset class="full"><legend>¿Cómo se sintió con la actividad? *</legend>@foreach($schema::OPINIONS as $key => $label)<label class="choice"><input type="radio" name="opinion" value="{{ $key }}" @checked($value('opinion') === $key) required> {{ $label }}</label>@endforeach</fieldset>
                <label class="full">Tipo de retroalimentación *<select name="feedback_type" required><option value="">Seleccione una opción</option>@foreach($schema::FEEDBACK_TYPES as $key => $label)<option value="{{ $key }}" @selected($value('feedback_type') === $key)>{{ $label }}</option>@endforeach</select></label>
                <label class="full">Comentario, sugerencia o queja<textarea name="comment" rows="5" maxlength="10000">{{ $value('comment') }}</textarea></label>
                <label class="full">¿Qué podemos hacer para mejorar?<textarea name="improvement" rows="4" maxlength="10000">{{ $value('improvement') }}</textarea><small>Complete al menos uno de los dos campos de texto.</small></label>
            </div>
            <button type="submit">Enviar opinión</button>
        </form>
    </section>
    <footer>ASONACOP · Buzón de opiniones. Este enlace no permite consultar opiniones anteriores.</footer>
</main>
</body>
</html>
