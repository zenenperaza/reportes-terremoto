<div class="form-grid two-cols">
<label>Donante *<select name="donante_id" required><option value="">Seleccione un donante</option>@foreach($donantes as $donante)<option value="{{ $donante->id }}" @selected((string)old('donante_id',$proyecto?->donante_id)===(string)$donante->id)>{{ $donante->nombre }}{{ $donante->estatus?'':' (Inactivo)' }}</option>@endforeach</select></label>
<label>Estado *<select name="estatus" required><option value="1" @selected((string)old('estatus',$proyecto?->estatus ?? true)==='1')>Activo</option><option value="0" @selected((string)old('estatus',$proyecto?->estatus ?? true)==='0')>Inactivo</option></select></label>
<label>C&oacute;digo *<input type="text" name="codigo" value="{{ old('codigo',$proyecto?->codigo) }}" maxlength="50" required></label>
<label>Descripci&oacute;n *<input type="text" name="descripcion" value="{{ old('descripcion',$proyecto?->descripcion) }}" maxlength="255" required></label>
<label>Fecha de inicio<input type="date" name="inicio" value="{{ old('inicio',$proyecto?->inicio?->format('Y-m-d')) }}"></label>
<label>Fecha de finalizaci&oacute;n<input type="date" name="fin" value="{{ old('fin',$proyecto?->fin?->format('Y-m-d')) }}"></label>
@php
    $selectedStates = array_map('strval', old('state_ids', $proyecto?->estados()->pluck('states.id')->all() ?? []));
    $selectedMunicipalities = array_map('strval', old('municipality_ids', $proyecto?->municipios()->pluck('municipalities.id')->all() ?? []));
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
</div>
@push('scripts')
<script>
$(function () {
    const states = $('#project-state-ids');
    const municipalities = $('#project-municipality-ids');
    const allMunicipalities = municipalities.find('option').clone();
    const initialMunicipalities = @json($selectedMunicipalities);

    states.select2({width: '100%', closeOnSelect: false, placeholder: states.data('placeholder')});
    municipalities.select2({width: '100%', closeOnSelect: false, placeholder: municipalities.data('placeholder')});

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
    };

    states.on('change', () => syncMunicipalities(false));
    syncMunicipalities(true);
});
</script>
@endpush
<div class="form-actions"><a class="button button-secondary" href="{{ route('proyectos.index') }}">Cancelar</a><button class="button button-primary" type="submit">{{ $submitLabel }}</button></div>
