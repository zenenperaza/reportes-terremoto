<div class="form-grid two-cols">
    <label>Nombre completo *
        <input type="text" name="name" value="{{ old('name', $managedUser?->name) }}" autocomplete="name" required autofocus>
    </label>
    @php
        $selectedProjects = array_map('strval', old('project_ids', $managedUser?->projects()->pluck('proyectos.id')->all() ?? []));
    @endphp
    <label class="span-two assigned-projects-field">Proyectos asignados *
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
    <div class="span-two permission-switch-card">
        <div class="permission-switch-copy">
            <span class="permission-switch-icon" aria-hidden="true"><i class="ri-checkbox-circle-line"></i></span>
            <div>
                <label class="permission-switch-title" for="can-mark-reported">Puede actualizar a reportado</label>
                <p>Permite consolidar beneficiarios pendientes mediante la opci&oacute;n &ldquo;Actualizar a Reportado&rdquo;.</p>
            </div>
        </div>
        <div class="form-check form-switch form-switch-lg permission-switch-control">
            <input class="form-check-input" type="checkbox" role="switch" id="can-mark-reported" name="can_mark_reported" value="1" @checked((bool) old('can_mark_reported', $managedUser?->can_mark_reported ?? false))>
            <label class="form-check-label" for="can-mark-reported"><span class="visually-hidden">Cambiar permiso para actualizar a reportado</span></label>
        </div>
    </div>
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
        $stateProjects = $projects->flatMap(fn ($project) => $project->estados->map(fn ($state) => ['state' => $state->id, 'project' => $project->id]))->groupBy('state')->map(fn ($rows) => $rows->pluck('project')->values());
        $municipalityProjects = $projects->flatMap(function ($project) {
            $municipalities = $project->municipios->isNotEmpty()
                ? $project->municipios
                : $project->estados->flatMap->municipalities;
            return $municipalities->map(fn ($municipality) => ['municipality' => $municipality->id, 'project' => $project->id]);
        })->groupBy('municipality')->map(fn ($rows) => $rows->pluck('project')->values());
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
                        <option value="{{ $state->id }}" data-project-ids='@json($stateProjects->get($state->id, []))' @selected(in_array((string) $state->id, $selectedStates, true))>{{ $state->name }}</option>
                    @endforeach
                </select>
                <small>Puede seleccionar varios con Ctrl o Cmd.</small>
            </label>
            <label>Municipios espec&iacute;ficos
                <select name="municipality_ids[]" id="assigned-municipality-ids" multiple size="8">
                    @foreach ($states as $state)
                        @foreach ($state->municipalities as $municipality)
                            <option value="{{ $municipality->id }}" data-state-id="{{ $state->id }}" data-project-ids='@json($municipalityProjects->get($municipality->id, []))' @selected(in_array((string) $municipality->id, $selectedMunicipalities, true))>{{ $state->name }} &mdash; {{ $municipality->name }}</option>
                        @endforeach
                    @endforeach
                </select>
                <small>No es necesario seleccionar municipios de un estado completo.</small>
            </label>
        </div>
    </div>
</div>
<script>
    const assignedProjects = document.getElementById('assigned-project-ids');
    const assignedStates = document.getElementById('assigned-state-ids');
    const assignedMunicipalities = document.getElementById('assigned-municipality-ids');
    const allStateOptions = [...assignedStates.options].map(option => option.cloneNode(true));
    const allMunicipalityOptions = [...assignedMunicipalities.options].map(option => option.cloneNode(true));
    const initialStates = @json($selectedStates);
    const initialMunicipalities = @json($selectedMunicipalities);

    const belongsToSelectedProject = (option, projectIds) => {
        const optionProjects = JSON.parse(option.dataset.projectIds || '[]').map(String);
        return optionProjects.some(projectId => projectIds.has(projectId));
    };
    const syncMunicipalitiesForSelectedStates = (projectIds, municipalitiesToKeep = new Set()) => {
        const selectedStateIds = new Set(
            [...assignedStates.selectedOptions]
                .map(option => option.value)
                .filter(value => value !== 'countrywide')
        );

        assignedMunicipalities.replaceChildren();
        if (selectedStateIds.size === 0) return;

        allMunicipalityOptions
            .filter(option => selectedStateIds.has(String(option.dataset.stateId)))
            .filter(option => belongsToSelectedProject(option, projectIds))
            .forEach(option => {
                const copy = option.cloneNode(true);
                copy.selected = municipalitiesToKeep.has(copy.value);
                assignedMunicipalities.append(copy);
            });
    };
    const syncProjectLocations = (preserveSelection = true) => {
        const projectIds = new Set([...assignedProjects.selectedOptions].map(option => option.value));
        const statesToKeep = new Set(preserveSelection ? initialStates : [...assignedStates.selectedOptions].map(option => option.value));
        const municipalitiesToKeep = new Set(preserveSelection ? initialMunicipalities : [...assignedMunicipalities.selectedOptions].map(option => option.value));

        assignedStates.replaceChildren();
        const countrywide = allStateOptions.find(option => option.value === 'countrywide');
        if (countrywide) assignedStates.append(countrywide.cloneNode(true));
        allStateOptions.filter(option => option.value !== 'countrywide' && belongsToSelectedProject(option, projectIds)).forEach(option => {
            const copy = option.cloneNode(true);
            copy.selected = statesToKeep.has(copy.value);
            assignedStates.append(copy);
        });

        const countrywideOption = assignedStates.querySelector('option[value="countrywide"]');
        const hasCountrywide = countrywideOption?.selected ?? false;
        if (hasCountrywide) {
            [...assignedStates.options].forEach(option => {
                if (option !== countrywideOption) option.selected = false;
            });
        }
        syncMunicipalitiesForSelectedStates(projectIds, municipalitiesToKeep);
        assignedStates.disabled = projectIds.size === 0;
        assignedMunicipalities.disabled = hasCountrywide || projectIds.size === 0 || assignedStates.selectedOptions.length === 0;
    };
    assignedStates.addEventListener('change', () => {
        const projectIds = new Set([...assignedProjects.selectedOptions].map(option => option.value));
        const municipalitySelection = new Set([...assignedMunicipalities.selectedOptions].map(option => option.value));
        const countrywideOption = assignedStates.querySelector('option[value="countrywide"]');
        const hasCountrywide = countrywideOption?.selected ?? false;

        if (hasCountrywide) {
            [...assignedStates.options].forEach(option => {
                if (option !== countrywideOption) option.selected = false;
            });
        }

        syncMunicipalitiesForSelectedStates(projectIds, municipalitySelection);
        assignedMunicipalities.disabled = hasCountrywide || projectIds.size === 0 || assignedStates.selectedOptions.length === 0;
    });
    syncProjectLocations(true);
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
    }).on('change', () => syncProjectLocations(false));
</script>
@endpush
<div class="form-actions">
    <a class="button button-secondary" href="{{ route('users.index') }}">Cancelar</a>
    <button class="button button-primary" type="submit">{{ $submitLabel }}</button>
</div>
