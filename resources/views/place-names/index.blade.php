@extends('layouts.app')

@section('title', 'Nombres del lugar | Respuesta ASONACOP')

@section('content')
<section class="page-heading compact-heading">
    <div>
        <p class="eyebrow">Catálogo compartido</p>
        <h1>Nombres específicos del lugar</h1>
        <p class="muted">Cada lugar define automáticamente su Estado, Municipio, Parroquia y Tipo de instalación.</p>
    </div>
</section>

<section class="content-card">
    <h2>Crear nombre del lugar</h2>
    <form method="post" action="{{ route('place-names.store') }}" class="place-name-form">
        @csrf
        <div class="form-grid two-cols">
            <label>Nombre específico del lugar *
                <input type="text" name="name" value="{{ old('name') }}" maxlength="200" required placeholder="Ej. Escuela Simón Bolívar">
            </label>
            <label>Estado *
                <select name="state_id" id="place_state_id" required>
                    <option value="">Seleccione el estado</option>
                    @foreach($states as $state)<option value="{{ $state->id }}" @selected(old('state_id') == $state->id)>{{ $state->name }}</option>@endforeach
                </select>
            </label>
            <label>Municipio *
                <select name="municipality_id" id="place_municipality_id" required><option value="">Seleccione primero el estado</option></select>
            </label>
            <label>Parroquia *
                <select name="parish_id" id="place_parish_id" required><option value="">Seleccione primero el municipio</option></select>
            </label>
            <label>Tipo de instalación / ubicación *
                <select name="installation_type" required>
                    <option value="">Seleccione una opción</option>
                    @foreach($installationTypes as $type)<option value="{{ $type }}" @selected(old('installation_type') === $type)>{{ $type }}</option>@endforeach
                </select>
            </label>
            <div class="span-two">
                <h3>Coordenadas de la ubicaci&oacute;n</h3>
                <small id="automatic-coordinate-status" class="muted" role="status" aria-live="polite"></small>
                <p class="muted">Latitud y longitud son obligatorias y se validarán contra el Estado y Municipio seleccionados. Altitud y precisión son opcionales.</p>
                <div class="current-coordinate-tools">
                    <button class="button button-secondary" type="button" id="show-current-coordinates">Mostrar mi ubicación actual</button>
                    <small id="current-coordinate-status" class="muted" role="status" aria-live="polite">Las coordenadas se mostrarán aquí para que pueda copiarlas y pegarlas.</small>
                </div>
                <small id="current-coordinate-values" class="current-coordinate-values" hidden>
                    Ubicación actual —
                    Latitud: <button type="button" class="coordinate-copy" id="copy-current-latitude" title="Copiar latitud"></button>
                    · Longitud: <button type="button" class="coordinate-copy" id="copy-current-longitude" title="Copiar longitud"></button>
                </small>
                <div class="form-grid four-cols">
                    <label>Latitud *<input type="number" id="place_latitude" name="latitude" value="{{ old('latitude') }}" step="0.0000001" min="0.5" max="12.7" required></label>
                    <label>Longitud *<input type="number" id="place_longitude" name="longitude" value="{{ old('longitude') }}" step="0.0000001" min="-74" max="-59" required></label>
                    <label>Altitud (m)<input type="number" name="altitude" value="{{ old('altitude') }}" step="0.01" min="-500" max="10000"></label>
                    <label>Precisión (m)<input type="number" name="gps_accuracy" value="{{ old('gps_accuracy') }}" step="0.01" min="0" max="100000"></label>
                </div>
            </div>
        </div>
        <div class="form-actions place-name-actions"><button class="button button-primary" type="submit">Crear lugar</button></div>
    </form>
</section>

<section class="content-card">
    <div class="card-heading"><div><h2>Lugares registrados</h2><p class="muted">Los lugares completos están disponibles en el formulario de registro.</p></div></div>
    @if($placeNames->isEmpty())
        <div class="empty-state"><p>Aún no se han creado nombres de lugares.</p></div>
    @else
        <div class="table-wrap"><table id="place-names-table" class="place-names-table">
            <thead><tr><th>Nombre</th><th>Ubicación</th><th>Coordenadas</th><th>Tipo de instalación</th><th>Creado por</th><th>Acciones</th></tr></thead>
            <tbody>@foreach($placeNames as $placeName)
                <tr>
                    <td>{{ $placeName->name }}</td>
                    <td>
                        @if($placeName->state && $placeName->municipality && $placeName->parish)
                            {{ $placeName->state->name }} · {{ $placeName->municipality->name }} · {{ $placeName->parish->name }}
                        @else
                            <span class="status status-submitted">Ubicación pendiente</span>
                        @endif
                    </td>
                    <td>@if($placeName->latitude !== null && $placeName->longitude !== null){{ $placeName->latitude }}, {{ $placeName->longitude }}@else—@endif</td>
                    <td>{{ $placeName->installation_type ?: '—' }}</td>
                    <td>{{ $placeName->creator?->name ?: '—' }}</td>
                    <td class="row-actions">
                        <a href="{{ route('place-names.edit', $placeName) }}">Editar</a>
                        <form method="post" action="{{ route('place-names.destroy', $placeName) }}" onsubmit="return confirm('¿Eliminar este nombre del catálogo?');">
                            @csrf @method('DELETE')
                            <button class="danger-link" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif
</section>

<script>
const placeSelect = id => document.getElementById(id);
const placeState = placeSelect('place_state_id'), placeMunicipality = placeSelect('place_municipality_id'), placeParish = placeSelect('place_parish_id');
const setPlaceOptions = (element, items, placeholder, selected = '') => {
    element.innerHTML = `<option value="">${placeholder}</option>` + items.map(item => `<option value="${item.id}" data-latitude="${item.latitude ?? ''}" data-longitude="${item.longitude ?? ''}" ${String(item.id) === String(selected) ? 'selected' : ''}>${item.name}</option>`).join('');
};
const loadPlaceOptions = async (element, url, placeholder, selected = '') => {
    const response = await fetch(url, {headers: {'Accept': 'application/json'}});
    setPlaceOptions(element, await response.json(), placeholder, selected);
};
placeState.addEventListener('change', async () => {
    setPlaceOptions(placeMunicipality, [], 'Cargando municipios');
    setPlaceOptions(placeParish, [], 'Seleccione primero el municipio');
    if (placeState.value) await loadPlaceOptions(placeMunicipality, `/ubicaciones/estados/${placeState.value}/municipios`, 'Seleccione el municipio');
});
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
const currentCoordinateButton = document.getElementById('show-current-coordinates');
const currentCoordinateStatus = document.getElementById('current-coordinate-status');
const currentCoordinateValues = document.getElementById('current-coordinate-values');
const currentLatitude = document.getElementById('copy-current-latitude');
const currentLongitude = document.getElementById('copy-current-longitude');
const copyCoordinate = async event => {
    const value = event.currentTarget.dataset.value;
    try {
        await navigator.clipboard.writeText(value);
        currentCoordinateStatus.textContent = `${value} copiado. Péguelo en el campo correspondiente.`;
    } catch (_) {
        currentCoordinateStatus.textContent = 'Seleccione el valor con el cursor para copiarlo manualmente.';
    }
};
currentLatitude.addEventListener('click', copyCoordinate);
currentLongitude.addEventListener('click', copyCoordinate);
currentCoordinateButton.addEventListener('click', () => {
    if (!navigator.geolocation) {
        currentCoordinateStatus.textContent = 'Este navegador no permite consultar la ubicación actual.';
        return;
    }
    currentCoordinateButton.disabled = true;
    currentCoordinateStatus.textContent = 'Consultando la ubicación actual…';
    navigator.geolocation.getCurrentPosition(position => {
        const latitude = Number(position.coords.latitude).toFixed(7);
        const longitude = Number(position.coords.longitude).toFixed(7);
        currentLatitude.textContent = latitude;
        currentLatitude.dataset.value = latitude;
        currentLongitude.textContent = longitude;
        currentLongitude.dataset.value = longitude;
        currentCoordinateValues.hidden = false;
        currentCoordinateStatus.textContent = 'Haga clic sobre cada valor para copiarlo.';
        currentCoordinateButton.disabled = false;
    }, () => {
        currentCoordinateStatus.textContent = 'No fue posible obtener la ubicación. Revise el permiso de ubicación del navegador.';
        currentCoordinateButton.disabled = false;
    }, {enableHighAccuracy: true, timeout: 15000, maximumAge: 0});
});
@if(old('state_id'))
loadPlaceOptions(placeMunicipality, `/ubicaciones/estados/{{ old('state_id') }}/municipios`, 'Seleccione el municipio', @json(old('municipality_id'))).then(() => {
    if (placeMunicipality.value) return loadPlaceOptions(placeParish, `/ubicaciones/municipios/${placeMunicipality.value}/parroquias`, 'Seleccione la parroquia', @json(old('parish_id')));
});
@endif

</script>
@endsection

@push('styles')
<link rel="stylesheet" href="/vendor/datatables/dataTables.dataTables.min.css">
<link rel="stylesheet" href="/vendor/datatables/buttons.dataTables.min.css">
<link rel="stylesheet" href="/css/beneficiary-datatable.css">
<style>
.place-name-form{margin-top:18px}.place-name-actions{margin-top:16px}.current-coordinate-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin:10px 0}.current-coordinate-values{display:block;margin:0 0 12px;color:var(--muted);font-size:12px}.coordinate-copy{border:0;background:transparent;color:#057bb4;font:inherit;font-weight:700;padding:0;cursor:pointer;text-decoration:underline}@media(max-width:560px){.place-name-actions .button,.current-coordinate-tools .button{width:100%}}
</style>
@endpush

@push('scripts')
<script src="/vendor/datatables/dataTables.min.js"></script>
<script src="/vendor/datatables/dataTables.buttons.min.js"></script>
<script src="/vendor/datatables/jszip.min.js"></script>
<script src="/vendor/datatables/pdfmake.min.js"></script>
<script src="/vendor/datatables/vfs_fonts.js"></script>
<script src="/vendor/datatables/buttons.html5.min.js"></script>
<script src="/vendor/datatables/buttons.print.min.js"></script>
<script>
const placeNamesTable = document.getElementById('place-names-table');
if (placeNamesTable && typeof DataTable !== 'undefined') {
    new DataTable(placeNamesTable, {
        layout: {
            topStart: ['pageLength', {
                buttons: [
                    {extend: 'copyHtml5', text: 'Copiar', exportOptions: {columns: [0, 1, 2, 3, 4]}},
                    {extend: 'csvHtml5', text: 'CSV', title: 'Lugares registrados', exportOptions: {columns: [0, 1, 2, 3, 4]}},
                    {extend: 'excelHtml5', text: 'Excel', title: 'Lugares registrados', exportOptions: {columns: [0, 1, 2, 3, 4]}},
                    {extend: 'pdfHtml5', text: 'PDF', title: 'Lugares registrados', orientation: 'landscape', pageSize: 'A4', exportOptions: {columns: [0, 1, 2, 3, 4]}},
                    {extend: 'print', text: 'Imprimir', title: 'Lugares registrados', exportOptions: {columns: [0, 1, 2, 3, 4]}},
                ],
            }],
            topEnd: 'search',
            bottomStart: 'info',
            bottomEnd: 'paging',
        },
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Todos']],
        order: [[0, 'asc']],
        columnDefs: [{targets: -1, orderable: false, searchable: false}],
        language: {
            emptyTable: 'No hay lugares registrados.',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ lugares',
            infoEmpty: 'Mostrando 0 a 0 de 0 lugares',
            infoFiltered: '(filtrado de _MAX_ lugares)',
            lengthMenu: 'Mostrar _MENU_ lugares',
            search: 'Buscar:',
            zeroRecords: 'No se encontraron lugares coincidentes',
            paginate: {first: 'Primero', last: '&Uacute;ltimo', next: 'Siguiente', previous: 'Anterior'},
        },
    });
}
</script>
@endpush
