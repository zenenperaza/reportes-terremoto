@extends('layouts.app')

@section('title', 'Editar lugar | Respuesta ASONACOP')

@section('content')
<section class="page-heading compact-heading">
    <div><p class="eyebrow">Catálogo compartido</p><h1>Editar nombre del lugar</h1><p class="muted">Actualice la ubicación que se cargará en los nuevos registros.</p></div>
</section>

<section class="content-card user-form-card">
    <form method="post" action="{{ route('place-names.update', $placeName) }}" class="place-name-form">
        @csrf @method('PUT')
        <div class="form-grid two-cols">
            <label class="span-two">Nombre específico del lugar *
                <input type="text" name="name" value="{{ old('name', $placeName->name) }}" maxlength="200" required>
            </label>
            <label>Estado *
                <select name="state_id" id="place_state_id" required><option value="">Seleccione el estado</option>@foreach($states as $state)<option value="{{ $state->id }}" @selected(old('state_id', $placeName->state_id) == $state->id)>{{ $state->name }}</option>@endforeach</select>
            </label>
            <label>Municipio *
                <select name="municipality_id" id="place_municipality_id" required><option value="">Seleccione el municipio</option>@foreach($municipalities as $municipality)<option value="{{ $municipality->id }}" @selected(old('municipality_id', $placeName->municipality_id) == $municipality->id)>{{ $municipality->name }}</option>@endforeach</select>
            </label>
            <label>Parroquia *
                <select name="parish_id" id="place_parish_id" required><option value="">Seleccione la parroquia</option>@foreach($parishes as $parish)<option value="{{ $parish->id }}" data-latitude="{{ $parish->latitude }}" data-longitude="{{ $parish->longitude }}" @selected(old('parish_id', $placeName->parish_id) == $parish->id)>{{ $parish->name }}</option>@endforeach</select>
            </label>
            <label>Tipo de instalación / ubicación *
                <select name="installation_type" required><option value="">Seleccione una opción</option>@foreach($installationTypes as $type)<option value="{{ $type }}" @selected(old('installation_type', $placeName->installation_type) === $type)>{{ $type }}</option>@endforeach</select>
            </label>
            <div class="span-two">
                <h3>Coordenadas de la ubicaci&oacute;n</h3>
                <small id="automatic-coordinate-status" class="muted" role="status" aria-live="polite"></small>
                <p class="muted">Latitud y longitud son obligatorias y se validarán contra el Estado y Municipio seleccionados. Altitud y precisión son opcionales.</p>
                <div class="form-grid four-cols">
                    <label>Latitud *<input type="number" id="place_latitude" name="latitude" value="{{ old('latitude', $placeName->latitude) }}" step="0.0000001" min="0.5" max="12.7" required></label>
                    <label>Longitud *<input type="number" id="place_longitude" name="longitude" value="{{ old('longitude', $placeName->longitude) }}" step="0.0000001" min="-74" max="-59" required></label>
                    <label>Altitud (m)<input type="number" name="altitude" value="{{ old('altitude', $placeName->altitude) }}" step="0.01" min="-500" max="10000"></label>
                    <label>Precisión (m)<input type="number" name="gps_accuracy" value="{{ old('gps_accuracy', $placeName->gps_accuracy) }}" step="0.01" min="0" max="100000"></label>
                </div>
            </div>
        </div>
        <div class="form-actions"><a class="button button-ghost" href="{{ route('place-names.index') }}">Cancelar</a><button class="button button-primary" type="submit">Guardar cambios</button></div>
    </form>
</section>

<script>
const placeSelect = id => document.getElementById(id);
const placeState = placeSelect('place_state_id'), placeMunicipality = placeSelect('place_municipality_id'), placeParish = placeSelect('place_parish_id');
const setPlaceOptions = (element, items, placeholder) => { element.innerHTML = `<option value="">${placeholder}</option>` + items.map(item => `<option value="${item.id}" data-latitude="${item.latitude ?? ''}" data-longitude="${item.longitude ?? ''}">${item.name}</option>`).join(''); };
const loadPlaceOptions = async (element, url, placeholder) => { const response = await fetch(url, {headers: {'Accept': 'application/json'}}); setPlaceOptions(element, await response.json(), placeholder); };
placeState.addEventListener('change', async () => { setPlaceOptions(placeMunicipality, [], 'Cargando municipios'); setPlaceOptions(placeParish, [], 'Seleccione primero el municipio'); if (placeState.value) await loadPlaceOptions(placeMunicipality, `/ubicaciones/estados/${placeState.value}/municipios`, 'Seleccione el municipio'); });
const placeLatitude = placeSelect('place_latitude'), placeLongitude = placeSelect('place_longitude');
const automaticCoordinateStatus = placeSelect('automatic-coordinate-status');
const applyAutomaticCoordinates = (latitude, longitude, source) => {
    if (latitude === '' || longitude === '' || !Number.isFinite(Number(latitude)) || !Number.isFinite(Number(longitude))) {
        automaticCoordinateStatus.textContent = 'No hay coordenadas de referencia. Ingréselas manualmente.';
        return;
    }
    placeLatitude.value = Number(latitude).toFixed(7);
    placeLongitude.value = Number(longitude).toFixed(7);
    automaticCoordinateStatus.textContent = `Coordenadas cargadas automáticamente desde ${source}.`;
};
placeMunicipality.addEventListener('change', async () => {
    setPlaceOptions(placeParish, [], 'Cargando parroquias');
    if (!placeMunicipality.value) return;
    const response = await fetch(`/ubicaciones/municipios/${placeMunicipality.value}/parroquias`, {headers: {'Accept': 'application/json'}});
    const parishes = await response.json();
    setPlaceOptions(placeParish, parishes, 'Seleccione la parroquia');
    const coordinates = parishes.filter(item => item.latitude !== null && item.longitude !== null && Number.isFinite(Number(item.latitude)) && Number.isFinite(Number(item.longitude)));
    if (!coordinates.length) return applyAutomaticCoordinates('', '', 'el municipio seleccionado');
    applyAutomaticCoordinates(
        coordinates.reduce((total, item) => total + Number(item.latitude), 0) / coordinates.length,
        coordinates.reduce((total, item) => total + Number(item.longitude), 0) / coordinates.length,
        'el municipio seleccionado'
    );
});
placeParish.addEventListener('change', () => {
    const option = placeParish.selectedOptions[0];
    if (option?.value) applyAutomaticCoordinates(option.dataset.latitude, option.dataset.longitude, 'la parroquia seleccionada');
});
</script>
@endsection

@push('styles')
<style>.place-name-form{display:grid;gap:18px}</style>
@endpush
