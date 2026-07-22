@extends('layouts.app')

@section('title', 'Nuevo registro | Respuesta ASONACOP')

@section('content')
@php($nameParts = preg_split('/\s+/', trim($user->name), 2))
<section class="page-heading compact-heading">
    <div>
        <p class="eyebrow">Formulario de respuesta</p>
        <h1>Registrar actividad</h1>
        <p class="muted">Cada clic en “Guardar beneficiario” registra inmediatamente la persona en la base de datos.</p>
    </div>
</section>

<form enctype="multipart/form-data" class="report-form" id="report-form" data-beneficiary-url="{{ route('beneficiaries.store') }}" data-location-reverse-url="{{ route('locations.reverse') }}" novalidate>
    @csrf
    <section class="form-section">
        <div class="section-heading"><span>1</span><div><h2>Actividad</h2><p>Si cambia cualquiera de estos encabezados, el próximo beneficiario iniciará un nuevo registro.</p></div></div>
        <div class="form-grid two-cols">
            <label>Sector programático *<select name="sector_id" id="sector_id" required><option value="">Seleccione el sector principal</option>@foreach($sectors as $sector)<option value="{{ $sector->id }}" @selected((string) $selectedSectorId === (string) $sector->id)>{{ $sector->name }}</option>@endforeach</select></label>
            <label>Actividad a reportar *<select name="activity_id" id="activity_id" required><option value="">Seleccione primero el sector</option>@foreach($activities as $activity)<option value="{{ $activity->id }}" @selected(old('activity_id') == $activity->id)>{{ $activity->title }}</option>@endforeach</select></label>
            <label class="span-two">Detalles adicionales de la actividad<textarea name="activity_details" rows="4" maxlength="5000" placeholder="Cantidades entregadas, temas de capacitación, logros o detalles relevantes.">{{ old('activity_details') }}</textarea></label>
        </div>
    </section>

    <section class="form-section">
        <div class="section-heading"><span>2</span><div><h2>Quién implementa</h2><p>Estos datos se conservan para cada beneficiario del mismo registro.</p></div></div>
        <div class="form-grid three-cols">
            <label>Fecha de atencion *<input type="date" name="report_date" value="{{ old('report_date', today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required></label>
            <label>Nombre *<input type="text" name="reporter_first_name" value="{{ old('reporter_first_name', $nameParts[0] ?? '') }}" required></label>
            <label>Apellido *<input type="text" name="reporter_last_name" value="{{ old('reporter_last_name', $nameParts[1] ?? '') }}" required></label>
            <label>Correo electrónico *<input type="email" name="reporter_email" value="{{ old('reporter_email', $user->email) }}" required></label>
            <label>Organización implementadora *
                <select name="organization" id="organization" required><option value="">Seleccione una organización</option>@foreach($organizations as $organization)<option value="{{ $organization }}" @selected(old('organization', 'ASONACOP') === $organization)>{{ $organization }}</option>@endforeach</select>
            </label>
            <label id="other-organization-field" hidden>Especifique otra organización<input type="text" name="other_organization" value="{{ old('other_organization') }}"></label>
        </div>
    </section>

    <section class="form-section">
        <div class="section-heading"><span>3</span><div><h2>Ubicación</h2><p>La información se mantiene mientras agregue beneficiarios a este registro.</p></div></div>
        <div class="gps-location-actions gps-location-actions-top">
            <button class="button button-secondary" type="button" id="gps-locate">Usar mi ubicación actual</button>
            <p class="gps-location-status" id="gps-location-status" role="status" aria-live="polite"></p>
        </div>
        <p class="gps-location-help">El navegador solicitará permiso para acceder a la ubicación. Se cargarán las coordenadas y se intentará seleccionar Estado, Municipio y Parroquia.</p>
        <p class="gps-geocoding-attribution">Ubicación administrativa aproximada: <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">© OpenStreetMap contributors</a>.</p>
        <div class="form-grid three-cols">
            <label>Estado *<select name="state_id" id="state_id" required><option value="">Seleccione el estado</option>@foreach($states as $state)<option value="{{ $state->id }}" @selected(old('state_id') == $state->id)>{{ $state->name }}</option>@endforeach</select></label>
            <label>Municipio *<select name="municipality_id" id="municipality_id" required><option value="">Seleccione primero el estado</option>@foreach($municipalities as $municipality)<option value="{{ $municipality->id }}" @selected(old('municipality_id') == $municipality->id)>{{ $municipality->name }}</option>@endforeach</select></label>
            <label>Parroquia *<select name="parish_id" id="parish_id" required><option value="">Seleccione primero el municipio</option>@foreach($parishes as $parish)<option value="{{ $parish->id }}" @selected(old('parish_id') == $parish->id)>{{ $parish->name }}</option>@endforeach</select></label>
            <label>Tipo de instalación / ubicación *<select name="installation_type" required><option value="">Seleccione una opción</option>@foreach($installationTypes as $type)<option value="{{ $type }}" @selected(old('installation_type') === $type)>{{ $type }}</option>@endforeach</select></label>
            <label class="span-two">Nombre específico del lugar *<input type="text" name="place_name" id="place_name" list="place-name-suggestions" autocomplete="off" maxlength="200" placeholder="Ej. Escuela Simón Bolívar o Comunidad El Carmen" value="{{ old('place_name') }}" required><datalist id="place-name-suggestions"></datalist><small>Escriba para ver lugares registrados anteriormente.</small></label>
        </div>
        <div class="gps-details"><p class="gps-details-heading">Ver o editar coordenadas GPS</p><p class="gps-location-help">También puede escribir las coordenadas manualmente. Si las indica, se verificarán antes de guardar que correspondan a Venezuela.</p><div class="form-grid four-cols"><label>Latitud<input type="number" step="0.0000001" min="0.5" max="12.7" name="latitude" value="{{ old('latitude') }}"></label><label>Longitud<input type="number" step="0.0000001" min="-74" max="-59" name="longitude" value="{{ old('longitude') }}"></label><label>Altitud (m)<input type="number" step="0.01" name="altitude" value="{{ old('altitude') }}"></label><label>Precisión (m)<input type="number" step="0.01" min="0" name="gps_accuracy" value="{{ old('gps_accuracy') }}"></label></div></div>
    </section>

    <section class="form-section">
        <div class="section-heading"><span>4</span><div><h2>Beneficiarios</h2><p>Complete una persona y guárdela. Los campos de esta sección se limpiarán, pero los encabezados permanecerán.</p></div></div>
        <fieldset class="beneficiary-entry">
            <legend id="beneficiary-entry-title">Registrar beneficiario</legend>
            <div class="form-grid beneficiary-form-grid">
                <label>Nombre y apellido *<input type="text" id="beneficiary_full_name" maxlength="150" autocomplete="name"></label>
                <label>Edad *<input type="number" id="beneficiary_age" min="0" max="120" inputmode="numeric"></label>
                <label>Sexo *<select id="beneficiary_sex"><option value="">Seleccione</option>@foreach($beneficiaryOptions['sexes'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></label>
                <label>Cédula<input type="text" id="beneficiary_national_id" maxlength="30" inputmode="numeric"></label>
                <label>Teléfono<input type="text" id="beneficiary_phone" maxlength="30" inputmode="tel"></label>
                <label>Discapacidad<select id="beneficiary_disability"><option value="">No especificada</option>@foreach($beneficiaryOptions['disabilities'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></label>
                <label>Indígena / etnia<select id="beneficiary_ethnicity"><option value="">No especificada</option>@foreach($beneficiaryOptions['ethnicities'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></label>
                <label id="beneficiary-pregnant-lactating-field">Embarazada o lactante<select id="beneficiary_pregnant_lactating"><option value="">No especificado</option>@foreach($beneficiaryOptions['pregnant_lactating'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></label>
            </div>
            <div class="beneficiary-recurrence-field"><label>Recurrente *<select id="beneficiary_is_recurrent"><option value="0" selected>No</option><option value="1">Sí</option></select></label><p class="muted">Indique esta condición de forma independiente, considerando la alerta de posibles coincidencias.</p></div>
            <div class="beneficiary-entry-actions"><p id="beneficiary-entry-error" class="field-error" hidden></p><p id="beneficiary-entry-success" class="field-success" hidden></p><button class="button button-secondary" type="button" id="save-beneficiary">Guardar beneficiario</button><button class="button button-ghost" type="button" id="cancel-beneficiary-edit" hidden>Cancelar edición</button></div>
        </fieldset>

        <div class="beneficiary-list-card">
            <div class="card-heading"><div><h3>Beneficiarios guardados</h3><p class="muted">Cada fila ya está registrada en la base de datos.</p></div><strong class="beneficiary-number" id="beneficiary-total">0</strong></div>
            <p id="beneficiary-empty" class="muted">Aún no ha guardado beneficiarios.</p>
            <div class="table-wrap" id="beneficiary-table-wrap" hidden><table class="beneficiary-table"><thead><tr><th>Nombre y apellido</th><th>Edad</th><th>Sexo</th><th>Cédula</th><th>Teléfono</th><th>Discapacidad</th><th>Indígena</th><th>Emb./lact.</th><th>Recurrente</th><th></th></tr></thead><tbody id="beneficiary-list"></tbody></table></div>
        </div>
    </section>

    <section class="form-section" hidden>
        <div class="section-heading"><span>3</span><div><h2>Grupos con necesidades específicas</h2><p>Se calculan automáticamente cada vez que guarda, edita o elimina un beneficiario.</p></div></div>
        <div class="beneficiary-summary"><div><span>Personas con discapacidad</span><strong id="summary-disability">0</strong></div><div><span>Población indígena</span><strong id="summary-ethnicity">0</strong></div><div><span>Embarazadas o en lactancia</span><strong id="summary-pregnancy">0</strong></div><div><span>Beneficiarios recurrentes</span><strong id="summary-recurrent">0</strong></div></div>
    </section>

    <section class="form-section" hidden>
        <div class="section-heading"><span>4</span><div><h2>Información adicional</h2><p>Las notas y evidencias se guardan junto al próximo beneficiario.</p></div></div>
        <div class="form-grid two-cols"><label class="span-two">Detalle cualitativo / notas de campo<textarea name="qualitative_notes" rows="5" maxlength="5000" placeholder="Describa brevemente logros, desafíos u observaciones relevantes.">{{ old('qualitative_notes') }}</textarea></label><label>Medio de verificación 1<input type="file" name="evidence_1" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx"><small>PDF, imagen, Word o Excel; máximo 10 MB.</small></label><label>Medio de verificación 2 (opcional)<input type="file" name="evidence_2" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx"><small>Máximo 10 MB.</small></label><label>Medio de verificación 3 (opcional)<input type="file" name="evidence_3" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xlsx"><small>Máximo 10 MB.</small></label></div>
    </section>
    <div class="form-actions"><a class="button button-ghost" href="{{ route('dashboard') }}">Cancelar</a><a class="button button-secondary" id="current-report-link" href="#" hidden>Ver registro guardado</a></div>
</form>

<script>
const select = (id) => document.getElementById(id);
const setOptions = (element, items, placeholder, selected = '') => { element.innerHTML = `<option value="">${placeholder}</option>` + items.map(item => `<option value="${item.id}" ${String(item.id) === String(selected) ? 'selected' : ''}>${item.name || item.title}</option>`).join(''); };
const loadOptions = async (element, url, placeholder, selected = '') => { const response = await fetch(url, {headers: {'Accept': 'application/json'}}); setOptions(element, await response.json(), placeholder, selected); };
const form = select('report-form'), state = select('state_id'), municipality = select('municipality_id'), parish = select('parish_id'), sector = select('sector_id'), activity = select('activity_id');
state.addEventListener('change', async () => { setOptions(municipality, [], 'Cargando municipios'); setOptions(parish, [], 'Seleccione primero el municipio'); if (state.value) await loadOptions(municipality, `/ubicaciones/estados/${state.value}/municipios`, 'Seleccione el municipio'); });
municipality.addEventListener('change', async () => { setOptions(parish, [], 'Cargando parroquias'); if (municipality.value) await loadOptions(parish, `/ubicaciones/municipios/${municipality.value}/parroquias`, 'Seleccione la parroquia'); });
sector.addEventListener('change', async () => { setOptions(activity, [], 'Cargando actividades'); if (sector.value) await loadOptions(activity, `/sectores/${sector.value}/actividades`, 'Seleccione la actividad'); });

const placeName = select('place_name'), placeNameSuggestions = select('place-name-suggestions');
let placeNameTimer;
const loadPlaceNameSuggestions = async () => {
    const term = placeName.value.trim();
    if (!term) { placeNameSuggestions.replaceChildren(); return; }
    const params = new URLSearchParams({q: term, state_id: state.value, municipality_id: municipality.value, parish_id: parish.value, installation_type: form.elements.installation_type.value});
    try {
        const response = await fetch(`{{ route('locations.places') }}?${params.toString()}`, {headers: {'Accept': 'application/json'}});
        const places = await response.json();
        placeNameSuggestions.replaceChildren(...places.map(place => { const option = document.createElement('option'); option.value = place; return option; }));
    } catch (_) { placeNameSuggestions.replaceChildren(); }
};
const schedulePlaceNameSuggestions = () => { clearTimeout(placeNameTimer); placeNameTimer = setTimeout(loadPlaceNameSuggestions, 250); };
placeName.addEventListener('input', schedulePlaceNameSuggestions);
[state, municipality, parish, form.elements.installation_type].forEach(element => element.addEventListener('change', () => { if (placeName.value.trim()) schedulePlaceNameSuggestions(); }));

const gpsLocateButton = select('gps-locate'), gpsLocationStatus = select('gps-location-status');
const latitudeInput = form.elements.latitude, longitudeInput = form.elements.longitude;
const setGpsLocationStatus = message => { gpsLocationStatus.textContent = message; };
const formatGpsValue = (value, decimals) => Number(value).toFixed(decimals);
const setCoordinateValidity = (message = '') => {
    latitudeInput.setCustomValidity(message);
    longitudeInput.setCustomValidity(message);
};
const applyDetectedLocation = async location => {
    if (!location?.state) return false;

    const previousState = state.value;
    const previousMunicipality = municipality.value;
    const previousParish = parish.value;
    state.value = location.state.id;
    setOptions(municipality, [], 'Cargando municipios');
    setOptions(parish, [], 'Seleccione primero el municipio');
    const municipalityToKeep = location.municipality?.id || (String(previousState) === String(location.state.id) ? previousMunicipality : '');
    await loadOptions(municipality, '/ubicaciones/estados/' + state.value + '/municipios', 'Seleccione el municipio', municipalityToKeep);

    if (!municipality.value) return true;

    const parishToKeep = location.parish?.id || (String(previousMunicipality) === String(municipality.value) ? previousParish : '');
    await loadOptions(parish, '/ubicaciones/municipios/' + municipality.value + '/parroquias', 'Seleccione la parroquia', parishToKeep);

    return true;
};
const reverseGeocode = async (latitude, longitude) => {
    const params = new URLSearchParams({latitude, longitude});
    const response = await fetch(form.dataset.locationReverseUrl + '?' + params.toString(), {headers: {'Accept': 'application/json'}});
    const result = await response.json().catch(() => ({}));

    if (!response.ok) throw new Error(result.message || 'No fue posible completar la ubicación administrativa.');

    await applyDetectedLocation(result.location);
    return result.message || 'Ubicación agregada correctamente.';
};
const validateCoordinates = async ({showStatus = true} = {}) => {
    const latitude = latitudeInput.value.trim(), longitude = longitudeInput.value.trim();

    if (!latitude && !longitude) {
        setCoordinateValidity();
        return true;
    }

    if (!latitude || !longitude) {
        const message = 'Indique latitud y longitud para validar las coordenadas.';
        setCoordinateValidity(message);
        if (showStatus) setGpsLocationStatus(message);
        return false;
    }

    setCoordinateValidity();
    if (!latitudeInput.checkValidity() || !longitudeInput.checkValidity()) {
        const message = 'Ingrese coordenadas dentro del rango geográfico de Venezuela.';
        setCoordinateValidity(message);
        if (showStatus) setGpsLocationStatus(message);
        return false;
    }

    if (showStatus) setGpsLocationStatus('Verificando que las coordenadas correspondan a Venezuela…');

    try {
        const message = await reverseGeocode(latitude, longitude);
        setCoordinateValidity();
        if (showStatus) setGpsLocationStatus(message);
        return true;
    } catch (error) {
        const message = error.message || 'No fue posible validar las coordenadas.';
        setCoordinateValidity(message);
        if (showStatus) setGpsLocationStatus(message);
        return false;
    }
};
const loadCurrentLocation = (automatically = false) => {
    if (!navigator.geolocation) { setGpsLocationStatus('Este navegador no permite obtener la ubicación. Ingrese las coordenadas manualmente.'); return; }
    gpsLocateButton.disabled = true;
    setGpsLocationStatus(automatically ? 'Solicitando permiso para cargar su ubicación actual…' : 'Buscando su ubicación actual…');
    navigator.geolocation.getCurrentPosition(async position => {
        const coordinates = position.coords;
        form.elements.latitude.value = formatGpsValue(coordinates.latitude, 7);
        form.elements.longitude.value = formatGpsValue(coordinates.longitude, 7);
        form.elements.gps_accuracy.value = formatGpsValue(coordinates.accuracy, 2);
        form.elements.altitude.value = coordinates.altitude === null ? '' : formatGpsValue(coordinates.altitude, 2);
        setCoordinateValidity();

        try {
            await validateCoordinates();
        } finally {
            gpsLocateButton.disabled = false;
        }
    }, error => {
        const messages = {1: 'Permiso de ubicación denegado. Puede permitirlo en el navegador o escribir las coordenadas manualmente.', 2: 'No fue posible determinar la ubicación. Intente nuevamente o ingrese las coordenadas manualmente.', 3: 'La ubicación tardó demasiado. Intente nuevamente o ingrese las coordenadas manualmente.'};
        gpsLocateButton.disabled = false; setGpsLocationStatus(messages[error.code] || 'No fue posible obtener la ubicación.');
    }, {enableHighAccuracy: true, timeout: 15000, maximumAge: 0});
};
gpsLocateButton.addEventListener('click', () => loadCurrentLocation());
[latitudeInput, longitudeInput].forEach(input => input.addEventListener('change', () => {
    if (latitudeInput.value.trim() && longitudeInput.value.trim()) void validateCoordinates();
    else setCoordinateValidity();
}));

const hasLocationData = () => [latitudeInput, longitudeInput, state, municipality, parish]
    .some(input => input.value.trim() !== '');

if (!hasLocationData()) {
    window.setTimeout(() => loadCurrentLocation(true), 300);
}

const beneficiaryFields = ['full_name', 'age', 'sex', 'national_id', 'phone', 'disability', 'ethnicity', 'pregnant_lactating', 'is_recurrent'];
const beneficiaryInputs = Object.fromEntries(beneficiaryFields.map(field => [field, select(`beneficiary_${field}`)]));
const pregnantLactatingField = select('beneficiary-pregnant-lactating-field');
const syncPregnantLactatingField = () => { const isMan = beneficiaryInputs.sex.value === 'Hombre'; pregnantLactatingField.hidden = isMan; if (isMan) beneficiaryInputs.pregnant_lactating.value = ''; };
const entryError = select('beneficiary-entry-error'), entrySuccess = select('beneficiary-entry-success'), beneficiaryList = select('beneficiary-list'), beneficiaryEmpty = select('beneficiary-empty'), beneficiaryTable = select('beneficiary-table-wrap'), saveButton = select('save-beneficiary');
const headerFields = ['report_date', 'reporter_first_name', 'reporter_last_name', 'reporter_email', 'organization', 'other_organization', 'state_id', 'municipality_id', 'parish_id', 'installation_type', 'place_name', 'sector_id', 'activity_id'];
let beneficiaries = [], activeReportId = null, activeHeaderSignature = null, beneficiaryEditId = null, isSaving = false;
let currentSummary = {total: 0, people_with_disabilities: 0, indigenous_people: 0, pregnant_or_lactating_women: 0};
const inputValue = field => beneficiaryInputs[field].value.trim();
const beneficiaryRecord = () => Object.fromEntries(beneficiaryFields.map(field => [field, inputValue(field)]));
const headerSignature = () => JSON.stringify(Object.fromEntries(headerFields.map(field => [field, form.elements[field]?.value.trim() || ''])));
const setMessage = (element, message = '') => { element.textContent = message; element.hidden = !message; };
const clearBeneficiaryEntry = () => { beneficiaryFields.forEach(field => beneficiaryInputs[field].value = ''); beneficiaryInputs.is_recurrent.value = '0'; syncPregnantLactatingField(); beneficiaryEditId = null; select('beneficiary-entry-title').textContent = 'Registrar beneficiario'; saveButton.textContent = 'Guardar beneficiario'; select('cancel-beneficiary-edit').hidden = true; recurrenceWarning.hidden = true; };
const requiredHeaderFields = [['report_date', 'fecha de registro'], ['reporter_first_name', 'nombre de quien registra'], ['reporter_last_name', 'apellido de quien registra'], ['reporter_email', 'correo electrónico'], ['organization', 'organización'], ['state_id', 'estado'], ['municipality_id', 'municipio'], ['parish_id', 'parroquia'], ['installation_type', 'tipo de instalación'], ['place_name', 'nombre del lugar'], ['sector_id', 'sector programático'], ['activity_id', 'actividad a reportar']];
const ensureReportContext = () => {
    const missing = requiredHeaderFields.find(([field]) => !form.elements[field].value.trim());
    if (!missing) return true;
    setMessage(entryError, `Antes de guardar, complete ${missing[1]}.`); form.elements[missing[0]].focus(); return false;
};
const beneficiaryValidationMessage = beneficiary => {
    const labels = {full_name: 'nombre y apellido', age: 'edad', sex: 'sexo', is_recurrent: 'recurrente'};
    const missing = Object.keys(labels).filter(field => beneficiary[field] === '');
    const errors = missing.length ? [`Complete los campos obligatorios: ${missing.map(field => labels[field]).join(', ')}.`] : [];
    if (beneficiary.age !== '' && (!Number.isInteger(Number(beneficiary.age)) || Number(beneficiary.age) < 0 || Number(beneficiary.age) > 120)) errors.push('Indique una edad válida entre 0 y 120 años.');
    return errors.join(' ');
};
const updateSummary = summary => { currentSummary = summary; select('beneficiary-total').textContent = Number(summary.total || 0).toLocaleString('es-VE'); select('summary-disability').textContent = summary.people_with_disabilities || 0; select('summary-ethnicity').textContent = summary.indigenous_people || 0; select('summary-pregnancy').textContent = summary.pregnant_or_lactating_women || 0; select('summary-recurrent').textContent = beneficiaries.filter(item => Boolean(item.is_recurrent)).length; };
function renderBeneficiaries() {
    beneficiaryList.replaceChildren();
    beneficiaries.forEach((beneficiary, index) => {
        const row = document.createElement('tr');
        [beneficiary.full_name, beneficiary.age, beneficiary.sex, beneficiary.national_id || '—', beneficiary.phone || '—', beneficiary.disability || 'No especificada', beneficiary.ethnicity || 'No especificada', beneficiary.pregnant_lactating || 'No especificado', beneficiary.is_recurrent ? 'Sí' : 'No'].forEach(value => { const cell = document.createElement('td'); cell.textContent = value; row.appendChild(cell); });
        const actions = document.createElement('td');
        const edit = document.createElement('button'); edit.type = 'button'; edit.className = 'table-action'; edit.textContent = 'Editar'; edit.addEventListener('click', () => { beneficiaryFields.forEach(field => beneficiaryInputs[field].value = field === 'is_recurrent' ? (beneficiary[field] ? '1' : '0') : String(beneficiary[field] ?? '')); syncPregnantLactatingField(); beneficiaryEditId = beneficiary.id; select('beneficiary-entry-title').textContent = `Editar beneficiario ${index + 1}`; saveButton.textContent = 'Actualizar beneficiario'; select('cancel-beneficiary-edit').hidden = false; select('beneficiary_full_name').focus(); });
        const remove = document.createElement('button'); remove.type = 'button'; remove.className = 'table-action danger-action'; remove.textContent = 'Eliminar'; remove.addEventListener('click', () => removeBeneficiary(beneficiary));
        actions.append(edit, remove); row.appendChild(actions); beneficiaryList.appendChild(row);
    });
    beneficiaryEmpty.hidden = beneficiaries.length > 0; beneficiaryTable.hidden = beneficiaries.length === 0; updateSummary(currentSummary);
}
const recurrenceWarning = document.createElement('p'); recurrenceWarning.className = 'recurrence-warning'; recurrenceWarning.hidden = true; recurrenceWarning.setAttribute('role', 'status'); beneficiaryInputs.is_recurrent.closest('label').before(recurrenceWarning);
let recurrenceCheck = 0;
const checkPossibleRecurrence = async () => {
    const beneficiary = beneficiaryRecord(), hasFallback = beneficiary.full_name && beneficiary.age !== '' && beneficiary.sex, hasLocation = state.value && municipality.value && parish.value;
    if (!activity.value || (!beneficiary.national_id && (!hasFallback || !hasLocation))) { recurrenceWarning.hidden = true; return; }
    const currentCheck = ++recurrenceCheck;
    const params = new URLSearchParams({activity_id: activity.value, state_id: state.value, municipality_id: municipality.value, parish_id: parish.value, national_id: beneficiary.national_id, full_name: beneficiary.full_name, age: beneficiary.age, sex: beneficiary.sex, exclude_beneficiary_id: beneficiaryEditId || ''});
    try {
        const response = await fetch(`{{ route('beneficiaries.recurrence') }}?${params.toString()}`, {headers: {'Accept': 'application/json'}}), result = await response.json();
        if (currentCheck !== recurrenceCheck) return;
        if (result.matches > 0) { recurrenceWarning.textContent = `Aviso: se encontró ${result.matches} coincidencia(s) para esta actividad por nombre, edad, sexo, ubicación y actividad. A continuación indique si es un Beneficiario Recurrente.`; recurrenceWarning.hidden = false; } else recurrenceWarning.hidden = true;
    } catch (_) { recurrenceWarning.hidden = true; }
};
const responseMessage = async response => { const payload = await response.json().catch(() => ({})); if (response.ok) return payload; const errors = payload.errors ? Object.values(payload.errors).flat()[0] : null; throw new Error(errors || payload.message || 'No se pudo guardar la información.'); };
const requestHeaders = {'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content};
async function saveBeneficiary() {
    if (isSaving || !ensureReportContext()) return;
    if (!await validateCoordinates()) {
        setMessage(entryError, 'Corrija las coordenadas GPS antes de guardar el beneficiario.');
        (latitudeInput.value.trim() ? longitudeInput : latitudeInput).focus();
        return;
    }
    const beneficiary = beneficiaryRecord();
    const validationMessage = beneficiaryValidationMessage(beneficiary);
    if (validationMessage) { setMessage(entryError, validationMessage); return; }
    const signature = headerSignature(), createsNewReport = !activeReportId || activeHeaderSignature !== signature;
    if (beneficiaryEditId && createsNewReport) { setMessage(entryError, 'Para editar, restaure los encabezados con los que se guardó este beneficiario.'); return; }
    const data = new FormData(form);
    beneficiaryFields.forEach(field => data.set(`beneficiary[${field}]`, beneficiary[field]));
    let url = form.dataset.beneficiaryUrl;
    if (beneficiaryEditId) { url = `{{ url('/beneficiarios') }}/${beneficiaryEditId}`; data.append('_method', 'PUT'); } else if (!createsNewReport) data.set('report_id', activeReportId);
    isSaving = true; saveButton.disabled = true; setMessage(entryError); setMessage(entrySuccess);
    try {
        const result = await responseMessage(await fetch(url, {method: 'POST', headers: requestHeaders, body: data}));
        if (beneficiaryEditId) beneficiaries = beneficiaries.map(item => item.id === result.beneficiary.id ? result.beneficiary : item);
        else beneficiaries = createsNewReport ? [result.beneficiary] : [...beneficiaries, result.beneficiary];
        if (result.report) { activeReportId = result.report.id; activeHeaderSignature = signature; const link = select('current-report-link'); link.href = result.report.url; link.hidden = false; }
        currentSummary = result.summary; renderBeneficiaries(); clearBeneficiaryEntry();
        ['evidence_1', 'evidence_2', 'evidence_3'].forEach(field => { form.elements[field].value = ''; });
        setMessage(entrySuccess, createsNewReport ? 'Beneficiario guardado. Se creó un nuevo registro con estos encabezados.' : result.message);
    } catch (error) { setMessage(entryError, error.message); }
    finally { isSaving = false; saveButton.disabled = false; }
}
async function removeBeneficiary(beneficiary) {
    if (!confirm(`¿Eliminar a ${beneficiary.full_name}?`)) return;
    setMessage(entryError); setMessage(entrySuccess);
    try {
        const result = await responseMessage(await fetch(`{{ url('/beneficiarios') }}/${beneficiary.id}`, {method: 'DELETE', headers: requestHeaders}));
        beneficiaries = beneficiaries.filter(item => item.id !== beneficiary.id); currentSummary = result.summary;
        if (result.report_deleted) { activeReportId = null; activeHeaderSignature = null; select('current-report-link').hidden = true; }
        renderBeneficiaries(); setMessage(entrySuccess, result.message);
    } catch (error) { setMessage(entryError, error.message); }
}
saveButton.addEventListener('click', saveBeneficiary); select('cancel-beneficiary-edit').addEventListener('click', clearBeneficiaryEntry); form.addEventListener('submit', event => event.preventDefault());
['full_name', 'national_id'].forEach(field => beneficiaryInputs[field].addEventListener('blur', checkPossibleRecurrence)); beneficiaryInputs.age.addEventListener('change', checkPossibleRecurrence); beneficiaryInputs.sex.addEventListener('change', () => { syncPregnantLactatingField(); checkPossibleRecurrence(); }); beneficiaryInputs.is_recurrent.addEventListener('focus', checkPossibleRecurrence);
const organization = select('organization'), otherOrganization = select('other-organization-field'); const syncOrganization = () => { const visible = organization.value === 'Otro Socio Implementador'; otherOrganization.hidden = !visible; otherOrganization.querySelector('input').required = visible; }; organization.addEventListener('change', syncOrganization); syncOrganization(); syncPregnantLactatingField(); renderBeneficiaries();
</script>
@endsection
