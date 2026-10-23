<div class="form-grid two-cols">
<label>Donante *<select name="donante_id" required><option value="">Seleccione un donante</option>@foreach($donantes as $donante)<option value="{{ $donante->id }}" @selected((string)old('donante_id',$proyecto?->donante_id)===(string)$donante->id)>{{ $donante->nombre }}{{ $donante->estatus?'':' (Inactivo)' }}</option>@endforeach</select></label>
<label>Estado *<select name="estatus" required><option value="1" @selected((string)old('estatus',$proyecto?->estatus ?? true)==='1')>Activo</option><option value="0" @selected((string)old('estatus',$proyecto?->estatus ?? true)==='0')>Inactivo</option></select></label>
<label>C&oacute;digo *<input type="text" name="codigo" value="{{ old('codigo',$proyecto?->codigo) }}" maxlength="50" required></label>
<label>Nombre (Alias)<input type="text" name="nombre_alias" value="{{ old('nombre_alias',$proyecto?->nombre_alias) }}" maxlength="255" placeholder="Nombre corto del proyecto"></label>
<label>Descripci&oacute;n *<input type="text" name="descripcion" value="{{ old('descripcion',$proyecto?->descripcion) }}" maxlength="255" required></label>
<label>Fecha de inicio<input type="date" name="inicio" value="{{ old('inicio',$proyecto?->inicio?->format('Y-m-d')) }}"></label>
<label>Fecha de finalizaci&oacute;n<input type="date" name="fin" value="{{ old('fin',$proyecto?->fin?->format('Y-m-d')) }}"></label>
@php
    $selectedStates = array_map('strval', old('state_ids', $proyecto?->estados()->pluck('states.id')->all() ?? []));
    $selectedMunicipalities = array_map('strval', old('municipality_ids', $proyecto?->municipios()->pluck('municipalities.id')->all() ?? []));
    $selectedPlaces = array_map('strval', old('place_name_ids', session()->hasOldInput() ? [] : ($proyecto?->lugares()->pluck('place_names.id')->all() ?? [])));
@endphp
<div class="span-two project-location-assignments">
    <div>
        <h3>Ubicaci&oacute;n del proyecto</h3>
        <p class="muted">Seleccione uno o varios estados. Los municipios son opcionales; si no selecciona ninguno, el proyecto abarcar&aacute; todos los municipios de esos estados.</p>
    </div>
    <div class="form-grid two-cols">
        <label>Estados asignados *
            <select name="state_ids[]" id="project-state-ids" multiple required data-placeholder="Seleccione uno o varios estados">
                @foreach($states as $state)
                    <option value="{{ $state->id }}" @selected(in_array((string)$state->id, $selectedStates, true))>{{ $state->name }}</option>
                @endforeach
            </select>
            <small>Puede seleccionar varios estados.</small>
        </label>
        <label>Municipios asignados (opcional)
            <select name="municipality_ids[]" id="project-municipality-ids" multiple data-placeholder="Todos los municipios de los estados seleccionados">
                @foreach($states as $state)
                    @foreach($state->municipalities as $municipality)
                        <option value="{{ $municipality->id }}" data-state-id="{{ $state->id }}" @selected(in_array((string)$municipality->id, $selectedMunicipalities, true))>{{ $state->name }} — {{ $municipality->name }}</option>
                    @endforeach
                @endforeach
            </select>
            <small>Déjelo en blanco para incluir todos los municipios de los estados seleccionados.</small>
        </label>
    </div>
</div>
<div class="span-two project-location-assignments">
    <h3>Lugares del proyecto</h3>
    <p class="muted">Seleccione los lugares del catálogo que podrán utilizarse al registrar actividades de este proyecto. Un lugar puede pertenecer a varios proyectos.</p>
    <label>Lugares asignados
        <select name="place_name_ids[]" id="project-place-ids" multiple data-placeholder="Seleccione uno o varios lugares">
            @foreach($placeNames as $place)
                <option value="{{ $place->id }}" data-state-id="{{ $place->state_id }}" data-municipality-id="{{ $place->municipality_id }}"
                    @selected(in_array((string) $place->id, $selectedPlaces, true))>{{ $place->name }} — {{ $place->state?->name }} / {{ $place->municipality?->name }} / {{ $place->parish?->name }}</option>
            @endforeach
        </select>
        <small>Use «Seleccionar todos los lugares disponibles» para marcarlos y luego quite individualmente los que no necesite.</small>
        <small>Solo se ofrecen lugares dentro de la ubicación del proyecto. Sin selección, no se ofrecerán lugares del catálogo al registrar. La atención comunitaria por parroquia mantiene su funcionamiento actual.</small>
        @can('manejar lugares')<small>Los lugares se siguen creando y modificando en <a href="{{ route('place-names.index') }}" target="_blank" rel="noopener">Nombres del lugar</a>.</small>@endcan
    </label>
</div>
</div>
@push('scripts')
<script>
$(function () {
    const states = $('#project-state-ids');
    const municipalities = $('#project-municipality-ids');
    const allMunicipalities = municipalities.find('option').clone();
    const initialMunicipalities = @json($selectedMunicipalities);
    const places = $('#project-place-ids');
    const allPlaces = places.find('option').clone();
    const selectAllPlacesValue = '__all_project_places__';

    states.select2({width: '100%', closeOnSelect: false, placeholder: states.data('placeholder')});
    municipalities.select2({width: '100%', closeOnSelect: false, placeholder: municipalities.data('placeholder')});
    places.select2({width: '100%', closeOnSelect: false, placeholder: places.data('placeholder')});

    const selectAllAvailablePlaces = () => {
        const ids = Array.from(places[0].options)
            .filter(option => option.value && option.value !== selectAllPlacesValue && !option.disabled)
            .map(option => option.value);
        // This is a one-time action, not a saved value or a permanent all-selected mode.
        places.val(ids).trigger('change');
        places.select2('close');
    };
    places.on('select2:select', event => {
        if (event.params.data.id === selectAllPlacesValue) selectAllAvailablePlaces();
    });

    const syncPlaces = () => {
        const stateIds = new Set((states.val() || []).map(String));
        const municipalityIds = new Set((municipalities.val() || []).map(String));
        const selected = new Set((places.val() || []).map(String));
        places.empty();
        allPlaces.each(function () {
            if (stateIds.has(String(this.dataset.stateId)) && (!municipalityIds.size || municipalityIds.has(String(this.dataset.municipalityId)))) {
                const option = $(this).clone();
                option.prop('selected', selected.has(String(this.value)));
                places.append(option);
            }
        });
        if (places[0].options.length > 0) {
            places.prepend(new Option('Seleccionar todos los lugares disponibles', selectAllPlacesValue));
        }
        places.prop('disabled', stateIds.size === 0).trigger('change.select2');
    };

    const syncMunicipalities = (preserveSelection = true) => {
        const stateIds = new Set((states.val() || []).map(String));
        const selected = preserveSelection ? new Set((municipalities.val() || initialMunicipalities).map(String)) : new Set();
        municipalities.empty();
        allMunicipalities.each(function () {
            if (stateIds.has(String(this.dataset.stateId))) {
                const option = $(this).clone();
                option.prop('selected', selected.has(String(this.value)));
                municipalities.append(option);
            }
        });
        municipalities.prop('disabled', stateIds.size === 0).trigger('change.select2');
        syncPlaces();
    };

    states.on('change', () => syncMunicipalities(false));
    municipalities.on('change', syncPlaces);
    syncMunicipalities(true);
});
</script>
@endpush
<div class="form-actions"><a class="button button-secondary" href="{{ route('proyectos.index') }}">Cancelar</a><button class="button button-primary" type="submit">{{ $submitLabel }}</button></div>
