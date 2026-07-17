@extends('layouts.app')

@section('title', 'Nuevo reporte | Respuesta UNICEF')

@section('content')
@php($nameParts = preg_split('/\s+/', trim($user->name), 2))
<section class="page-heading compact-heading">
    <div><p class="eyebrow">Formulario de respuesta</p><h1>Registrar actividad</h1><p class="muted">Los campos marcados con * son obligatorios. Las cifras de beneficiarios deben coincidir con su desagregación.</p></div>
</section>

<form method="post" action="{{ route('reports.store') }}" enctype="multipart/form-data" class="report-form" id="report-form">
    @csrf
    <section class="form-section">
        <div class="section-heading"><span>1</span><div><h2>Quién implementa</h2><p>Datos de la persona y organización que presenta el reporte.</p></div></div>
        <div class="form-grid three-cols">
            <label>Fecha de reporte *<input type="date" name="report_date" value="{{ old('report_date', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required></label>
            <label>Nombre *<input type="text" name="reporter_first_name" value="{{ old('reporter_first_name', $nameParts[0] ?? '') }}" required></label>
            <label>Apellido *<input type="text" name="reporter_last_name" value="{{ old('reporter_last_name', $nameParts[1] ?? '') }}" required></label>
            <label>Correo electrónico *<input type="email" name="reporter_email" value="{{ old('reporter_email', $user->email) }}" required></label>
            <label>Organización implementadora *
                <select name="organization" id="organization" required><option value="">Seleccione una organización</option>@foreach($organizations as $organization)<option value="{{ $organization }}" @selected(old('organization') === $organization)>{{ $organization }}</option>@endforeach</select>
            </label>
            <label id="other-organization-field">Especifique otra organización<input type="text" name="other_organization" value="{{ old('other_organization') }}"></label>
        </div>
    </section>

    <section class="form-section">
        <div class="section-heading"><span>2</span><div><h2>Dónde</h2><p>Ubicación, instalación y coordenadas de la actividad.</p></div></div>
        <div class="form-grid three-cols">
            <label>Estado *<select name="state_id" id="state_id" required><option value="">Seleccione el estado</option>@foreach($states as $state)<option value="{{ $state->id }}" @selected(old('state_id') == $state->id)>{{ $state->name }}</option>@endforeach</select></label>
            <label>Municipio *<select name="municipality_id" id="municipality_id" required><option value="">Seleccione primero el estado</option>@foreach($municipalities as $municipality)<option value="{{ $municipality->id }}" @selected(old('municipality_id') == $municipality->id)>{{ $municipality->name }}</option>@endforeach</select></label>
            <label>Parroquia *<select name="parish_id" id="parish_id" required><option value="">Seleccione primero el municipio</option>@foreach($parishes as $parish)<option value="{{ $parish->id }}" @selected(old('parish_id') == $parish->id)>{{ $parish->name }}</option>@endforeach</select></label>
            <label>Tipo de instalación / ubicación *<select name="installation_type" required><option value="">Seleccione una opción</option>@foreach($installationTypes as $type)<option value="{{ $type }}" @selected(old('installation_type') === $type)>{{ $type }}</option>@endforeach</select></label>
            <label class="span-two">Nombre específico del lugar *<input type="text" name="place_name" maxlength="200" placeholder="Ej. Escuela Simón Bolívar o Comunidad El Carmen" value="{{ old('place_name') }}" required></label>
        </div>
        <details class="gps-details"><summary>Agregar coordenadas GPS</summary><div class="form-grid four-cols"><label>Latitud<input type="number" step="0.0000001" min="-90" max="90" name="latitude" value="{{ old('latitude') }}"></label><label>Longitud<input type="number" step="0.0000001" min="-180" max="180" name="longitude" value="{{ old('longitude') }}"></label><label>Altitud (m)<input type="number" step="0.01" name="altitude" value="{{ old('altitude') }}"></label><label>Precisión (m)<input type="number" step="0.01" min="0" name="gps_accuracy" value="{{ old('gps_accuracy') }}"></label></div></details>
    </section>

    <section class="form-section">
        <div class="section-heading"><span>3</span><div><h2>Qué</h2><p>Sector programático y actividad realizada.</p></div></div>
        <div class="form-grid two-cols">
            <label>Sector programático *<select name="sector_id" id="sector_id" required><option value="">Seleccione el sector principal</option>@foreach($sectors as $sector)<option value="{{ $sector->id }}" @selected(old('sector_id') == $sector->id)>{{ $sector->name }}</option>@endforeach</select></label>
            <label>Actividad a reportar *<select name="activity_id" id="activity_id" required><option value="">Seleccione primero el sector</option>@foreach($activities as $activity)<option value="{{ $activity->id }}" @selected(old('activity_id') == $activity->id)>{{ $activity->title }}</option>@endforeach</select></label>
            <label class="span-two">Detalles adicionales de la actividad<textarea name="activity_details" rows="4" maxlength="5000" placeholder="Cantidades entregadas, temas de capacitación, logros o detalles relevantes.">{{ old('activity_details') }}</textarea></label>
        </div>
    </section>

    <section class="form-section">
        <div class="section-heading"><span>4</span><div><h2>A quién</h2><p>Beneficiarios y desagregación por sexo y edad.</p></div></div>
        <div class="form-grid two-cols inline-choice">
            <fieldset><legend>¿Son beneficiarios recurrentes? *</legend><label class="radio-label"><input type="radio" name="recurrence_status" value="recurrente" @checked(old('recurrence_status') === 'recurrente') required> Recurrentes</label><label class="radio-label"><input type="radio" name="recurrence_status" value="no_recurrente" @checked(old('recurrence_status') === 'no_recurrente')> No recurrentes</label></fieldset>
            <label>Total de beneficiarios alcanzados *<input type="number" id="total_beneficiaries" name="total_beneficiaries" min="0" value="{{ old('total_beneficiaries') }}" required><small id="breakdown-message">La suma de la desagregación debe coincidir con este total.</small></label>
        </div>
        <div class="scheme-picker"><label>Esquema de desagregación *<select name="beneficiary_scheme" id="beneficiary_scheme" required>@foreach($breakdownSchemes as $schemeKey => $scheme)<option value="{{ $schemeKey }}" @selected(old('beneficiary_scheme', 'tradicional') === $schemeKey)>{{ $scheme['label'] }}</option>@endforeach</select></label><p>Seleccione el esquema que corresponde a la actividad; no duplique las personas entre esquemas.</p></div>
        @foreach($breakdownSchemes as $schemeKey => $scheme)
            <div class="breakdown-grid" data-scheme="{{ $schemeKey }}">
                @foreach($scheme['fields'] as $field => $label)
                    <label>{{ $label }}<input type="number" min="0" name="beneficiary_breakdown[{{ $field }}]" value="{{ old('beneficiary_breakdown.'.$field, 0) }}" data-breakdown-input></label>
                @endforeach
            </div>
        @endforeach
        <p class="breakdown-total">Total desagregado: <strong id="breakdown-total">0</strong></p>
    </section>

    <section class="form-section">
        <div class="section-heading"><span>5</span><div><h2>Grupos con necesidades específicas</h2><p>Estos valores son subconjuntos del total de personas alcanzadas.</p></div></div>
        <div class="form-grid three-cols"><label>Personas con discapacidad<input type="number" min="0" name="people_with_disabilities" value="{{ old('people_with_disabilities', 0) }}"></label><label>Población indígena<input type="number" min="0" name="indigenous_people" value="{{ old('indigenous_people', 0) }}"></label><label>Mujeres embarazadas o en lactancia<input type="number" min="0" name="pregnant_or_lactating_women" value="{{ old('pregnant_or_lactating_women', 0) }}"></label></div>
    </section>

    <section class="form-section">
        <div class="section-heading"><span>6</span><div><h2>Reporte cualitativo y cierre</h2><p>Incluya contexto, logros, desafíos y los soportes disponibles.</p></div></div>
        <div class="form-grid two-cols"><label class="span-two">Detalle cualitativo / notas de campo<textarea name="qualitative_notes" rows="5" maxlength="5000" placeholder="Describa brevemente logros, desafíos u observaciones relevantes.">{{ old('qualitative_notes') }}</textarea></label><label>Medio de verificación 1<input type="file" name="evidence_1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx"><small>PDF, imagen, Word o Excel; máximo 10 MB.</small></label><label>Medio de verificación 2 (opcional)<input type="file" name="evidence_2" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx"><small>Máximo 10 MB.</small></label><label>Medio de verificación 3 (opcional)<input type="file" name="evidence_3" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx"><small>Máximo 10 MB.</small></label></div>
    </section>
    <div class="form-actions"><a class="button button-ghost" href="{{ route('dashboard') }}">Cancelar</a><button class="button button-primary" type="submit">Enviar reporte</button></div>
</form>

<script>
const select = (id) => document.getElementById(id);
const setOptions = (element, items, placeholder, selected) => { element.innerHTML = `<option value="">${placeholder}</option>` + items.map(item => `<option value="${item.id}" ${String(item.id) === String(selected) ? 'selected' : ''}>${item.name || item.title}</option>`).join(''); };
const loadOptions = async (element, url, placeholder, selected = '') => { const response = await fetch(url, {headers: {'Accept': 'application/json'}}); setOptions(element, await response.json(), placeholder, selected); };
const state = select('state_id'), municipality = select('municipality_id'), parish = select('parish_id'), sector = select('sector_id'), activity = select('activity_id');
state.addEventListener('change', async () => { setOptions(municipality, [], 'Cargando municipios'); setOptions(parish, [], 'Seleccione primero el municipio'); if (state.value) await loadOptions(municipality, `/ubicaciones/estados/${state.value}/municipios`, 'Seleccione el municipio'); });
municipality.addEventListener('change', async () => { setOptions(parish, [], 'Cargando parroquias'); if (municipality.value) await loadOptions(parish, `/ubicaciones/municipios/${municipality.value}/parroquias`, 'Seleccione la parroquia'); });
sector.addEventListener('change', async () => { setOptions(activity, [], 'Cargando actividades'); if (sector.value) await loadOptions(activity, `/sectores/${sector.value}/actividades`, 'Seleccione la actividad'); });
const scheme = select('beneficiary_scheme'), total = select('total_beneficiaries'), totalLabel = select('breakdown-total'), totalMessage = select('breakdown-message');
function syncBreakdown() { document.querySelectorAll('[data-scheme]').forEach(box => { const active = box.dataset.scheme === scheme.value; box.hidden = !active; box.querySelectorAll('input').forEach(input => input.disabled = !active); }); updateBreakdown(); }
function updateBreakdown() { const value = [...document.querySelectorAll('[data-scheme]:not([hidden]) [data-breakdown-input]')].reduce((sum, input) => sum + (parseInt(input.value || '0', 10) || 0), 0); totalLabel.textContent = value.toLocaleString('es-VE'); const target = parseInt(total.value || '0', 10) || 0; totalMessage.textContent = value === target ? 'La desagregación coincide con el total.' : `Faltan o sobran ${Math.abs(target - value).toLocaleString('es-VE')} personas para coincidir con el total.`; totalMessage.classList.toggle('is-valid', value === target); }
scheme.addEventListener('change', syncBreakdown); total.addEventListener('input', updateBreakdown); document.querySelectorAll('[data-breakdown-input]').forEach(input => input.addEventListener('input', updateBreakdown));
const organization = select('organization'), otherOrganization = select('other-organization-field'); function syncOrganization() { const visible = organization.value === 'Otro Socio Implementador'; otherOrganization.hidden = !visible; otherOrganization.querySelector('input').required = visible; } organization.addEventListener('change', syncOrganization); syncOrganization(); syncBreakdown();
</script>
@endsection
