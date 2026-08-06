<div class="form-grid two-cols">
    <label>Nombre completo *
        <input type="text" name="name" value="{{ old('name', $managedUser?->name) }}" autocomplete="name" required autofocus>
    </label>
    @php
        $selectedProjects = array_map('strval', old('project_ids', $managedUser?->projects()->pluck('proyectos.id')->all() ?? []));
    @endphp
    <label class="span-two">Proyectos asignados *
        <select name="project_ids[]" id="assigned-project-ids" multiple required data-placeholder="Busque y seleccione uno o varios proyectos">
            @foreach($projects as $project)
                <option value="{{ $project->id }}" @selected(in_array((string)$project->id, $selectedProjects, true))>{{ $project->codigo }} — {{ $project->descripcion }} ({{ $project->donante->nombre }})</option>
            @endforeach
        </select>
        <small>Escriba para buscar. Puede seleccionar uno o varios proyectos.</small>
    </label>
    <label>Correo electr&oacute;nico *
        <input type="email" name="email" value="{{ old('email', $managedUser?->email) }}" autocomplete="email" required>
    </label>
    <label>Rol *
        <select name="role" required>
            @foreach ($roleLabels as $role => $label)
                <option value="{{ $role }}" @selected(old('role', $managedUser?->role ?? 'reporter') === $role)>{{ $label }}</option>
            @endforeach
        </select>
    </label>
    <label>Estado *
        <select name="is_active" required>
            <option value="1" @selected((string) old('is_active', $managedUser?->is_active ?? true) === '1')>Activo</option>
            <option value="0" @selected((string) old('is_active', $managedUser?->is_active ?? true) === '0')>Inactivo</option>
        </select>
    </label>
    <label>{{ $managedUser ? 'Nueva contraseña (opcional)' : 'Contraseña *' }}
        <input type="password" name="password" autocomplete="new-password" @required(! $managedUser)>
    </label>
    <label class="span-two">{{ $managedUser ? 'Confirmar nueva contraseña' : 'Confirmar contraseña *' }}
        <input type="password" name="password_confirmation" autocomplete="new-password" @required(! $managedUser)>
    </label>
    @php
        $selectedStates = array_map('strval', old('state_ids', $managedUser?->assignedStates()->pluck('states.id')->all() ?? []));
        $selectedMunicipalities = array_map('strval', old('municipality_ids', $managedUser?->assignedMunicipalities()->pluck('municipalities.id')->all() ?? []));
        $defaultCountrywideAccess = (bool) ($managedUser?->isAdministrator() || $managedUser?->countrywide_access);
        $hasCountrywideAccess = filter_var(old('countrywide_access', $defaultCountrywideAccess), FILTER_VALIDATE_BOOLEAN);
    @endphp
    <div class="span-two user-location-assignments" id="user-location-assignments">
        <div>
            <h3>Asignaciones geogr&aacute;ficas</h3>
            <p class="muted">Un estado seleccionado concede acceso a todos sus municipios. Use municipios espec&iacute;ficos para accesos parciales.</p>
        </div>
        <div class="form-grid two-cols">
            <label>Estados completos
                <select name="state_ids[]" id="assigned-state-ids" multiple size="8">
                    <option value="countrywide" @selected($hasCountrywideAccess)>Todo el pa&iacute;s</option>
                    @foreach ($states as $state)
                        <option value="{{ $state->id }}" @selected(in_array((string) $state->id, $selectedStates, true))>{{ $state->name }}</option>
                    @endforeach
                </select>
                <small>Puede seleccionar varios con Ctrl o Cmd.</small>
            </label>
            <label>Municipios espec&iacute;ficos
                <select name="municipality_ids[]" id="assigned-municipality-ids" multiple size="8">
                    @foreach ($states as $state)
                        <optgroup label="{{ $state->name }}" data-state-id="{{ $state->id }}">
                            @foreach ($state->municipalities as $municipality)
                                <option value="{{ $municipality->id }}" data-state-id="{{ $state->id }}" @selected(in_array((string) $municipality->id, $selectedMunicipalities, true))>{{ $municipality->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <small>No es necesario seleccionar municipios de un estado completo.</small>
            </label>
        </div>
    </div>
</div>
<script>
    const assignedStates = document.getElementById('assigned-state-ids');
    const assignedMunicipalities = document.getElementById('assigned-municipality-ids');
    const syncMunicipalityOptions = () => {
        const countrywideOption = assignedStates.querySelector('option[value="countrywide"]');
        const countrywide = countrywideOption.selected;
        if (countrywide) {
            [...assignedStates.options].forEach(option => {
                if (option !== countrywideOption) option.selected = false;
            });
        }
        const selectedStateIds = new Set([...assignedStates.selectedOptions]
            .map(option => option.value).filter(value => value !== 'countrywide'));
        assignedMunicipalities.querySelectorAll('optgroup').forEach(group => {
            const visible = !countrywide && selectedStateIds.has(group.dataset.stateId);
            group.hidden = !visible;
            group.disabled = !visible;
            group.querySelectorAll('option').forEach(option => {
                option.hidden = !visible;
                option.disabled = !visible;
                if (!visible) option.selected = false;
            });
        });
        assignedMunicipalities.disabled = countrywide || selectedStateIds.size === 0;
    };
    assignedStates.addEventListener('change', syncMunicipalityOptions);
    syncMunicipalityOptions();
</script>
@push('scripts')
<script>
    $('#assigned-project-ids').select2({
        width: '100%',
        placeholder: $('#assigned-project-ids').data('placeholder'),
        closeOnSelect: false,
        language: {
            noResults: () => 'No se encontraron proyectos',
            searching: () => 'Buscando…'
        }
    });
</script>
@endpush
<div class="form-actions">
    <a class="button button-secondary" href="{{ route('users.index') }}">Cancelar</a>
    <button class="button button-primary" type="submit">{{ $submitLabel }}</button>
</div>
