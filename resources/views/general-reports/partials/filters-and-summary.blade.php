<section class="card general-filter-card">
    <div class="card-header"><div><h2 class="card-title mb-1">Filtros del informe</h2><p class="text-muted mb-0">Combine uno o varios criterios para actualizar todos los resultados.</p></div></div>
    <div class="card-body">
        <form method="get" id="general-report-filters" class="row g-3" data-locations-url="{{ $locationsRoute }}">
            @foreach(['attention_from' => ['attention', 'Fecha de atenciÃ³n desde'], 'attention_to' => ['attention', 'Fecha de atenciÃ³n hasta'], 'registered_from' => ['registered', 'Fecha de registro desde'], 'registered_to' => ['registered', 'Fecha de registro hasta']] as $field => [$dateGroup, $label])
                @php($bounds = $dateBounds[$dateGroup])
                <div class="col-xl-3 col-md-6">
                    <label class="form-label" for="general_{{ $field }}">{{ $label }}</label>
                    <input class="form-control" id="general_{{ $field }}" type="date" name="{{ $field }}"
                        value="{{ $filters[$field] ?? '' }}"
                        @if($bounds['min'] && $bounds['max']) min="{{ $bounds['min'] }}" max="{{ $bounds['max'] }}" @else disabled @endif
                        aria-describedby="general_{{ $field }}_help">
                    <small class="form-text text-muted" id="general_{{ $field }}_help">
                        @if($bounds['min'] && $bounds['max'])
                            Disponible: {{ \Illuminate\Support\Carbon::parse($bounds['min'])->format('d/m/Y') }} al {{ \Illuminate\Support\Carbon::parse($bounds['max'])->format('d/m/Y') }}.
                        @else
                            Sin fechas registradas disponibles.
                        @endif
                    </small>
                </div>
            @endforeach

            <div class="col-xl-2 col-md-4"><label class="form-label">Edad desde</label><input class="form-control" id="general_age_from" type="number" name="age_from" min="0" max="120" value="{{ $filters['age_from'] ?? '' }}" placeholder="0"></div>
            <div class="col-xl-2 col-md-4"><label class="form-label">Edad hasta</label><input class="form-control" id="general_age_to" type="number" name="age_to" min="0" max="120" value="{{ $filters['age_to'] ?? '' }}" placeholder="120"></div>
            <div class="col-xl-4 col-md-4"><label class="form-label">Grupo etario</label><select class="form-select" id="general_age_group" name="age_group"><option value="">Todos</option>@foreach($ageGroups as $value => $group)<option value="{{ $value }}" @selected(($filters['age_group'] ?? '') === $value)>{{ $group['label'] }}</option>@endforeach</select><small class="form-text text-muted">Use el rango de edad o el grupo etario, no ambos.</small></div>
            <div class="col-xl-4 col-md-6"><label class="form-label">Sexo</label><select class="form-select" name="sex"><option value="">Todos</option>@foreach(config('reports.beneficiary_options.sexes') as $sex)<option value="{{ $sex }}" @selected(($filters['sex'] ?? '') === $sex)>{{ $sex }}</option>@endforeach</select></div>

            <div class="col-xl-4 col-md-6"><label class="form-label" for="general_state_id">Estado</label><select class="form-select" name="state_id[]" id="general_state_id" multiple aria-describedby="general_states_help">@foreach($states as $state)<option value="{{ $state->id }}" @selected(in_array($state->id, $filters['state_id'], true))>{{ $state->name }}</option>@endforeach</select><small id="general_states_help" class="form-text text-muted">Seleccione uno o varios. Sin selecci&oacute;n se incluyen todos los estados.</small></div>
            <div class="col-xl-4 col-md-6"><label class="form-label" for="general_municipality_id">Municipio</label><select class="form-select" name="municipality_id" id="general_municipality_id"><option value="">Todos</option>@foreach($municipalities as $municipality)<option value="{{ $municipality['id'] }}" @selected(($filters['municipality_id'] ?? '') == $municipality['id'])>{{ $municipality['name'] }}</option>@endforeach</select></div>
            <div class="col-xl-4 col-md-6"><label class="form-label" for="general_parish_id">Parroquia</label><select class="form-select" name="parish_id" id="general_parish_id"><option value="">Todas</option>@foreach($parishes as $parish)<option value="{{ $parish['id'] }}" @selected(($filters['parish_id'] ?? '') == $parish['id'])>{{ $parish['name'] }}</option>@endforeach</select></div>
            <div id="general-locations-error" class="col-12 text-danger" role="alert" hidden>No se pudieron cargar municipios y parroquias. <button type="button" class="btn btn-outline-danger btn-sm" id="general-locations-retry">Reintentar</button></div>

            <div class="col-xl-4 col-md-6"><label class="form-label">Tipo de atenci&oacute;n</label><select class="form-select" name="installation_type"><option value="">Todos</option>@foreach($installationTypes as $type)<option value="{{ $type }}" @selected(($filters['installation_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div class="col-xl-4 col-md-6"><label class="form-label">Nombre del lugar</label><select class="form-select" name="place_name"><option value="">Todos</option>@foreach($places as $place)<option value="{{ $place }}" @selected(($filters['place_name'] ?? '') === $place)>{{ $place }}</option>@endforeach</select></div>
            <div class="col-xl-4 col-md-6"><label class="form-label">Sector program&aacute;tico</label><select class="form-select" name="sector_id" id="general_sector_id"><option value="">Todos</option>@foreach($sectors as $sector)<option value="{{ $sector->id }}" @selected(($filters['sector_id'] ?? '') == $sector->id)>{{ $sector->name }}</option>@endforeach</select></div>
            <div class="col-xl-4 col-md-6"><label class="form-label">Recurrente</label><select class="form-select" name="is_recurrent"><option value="">Todos</option><option value="1" @selected(($filters['is_recurrent'] ?? '') === '1')>S&iacute;</option><option value="0" @selected(($filters['is_recurrent'] ?? '') === '0')>No</option></select></div>
            <div class="col-xl-4 col-md-6"><label class="form-label">Reportado</label><select class="form-select" name="reported"><option value="">Todos</option><option value="1" @selected(($filters['reported'] ?? '') === '1')>S&iacute;</option><option value="0" @selected(($filters['reported'] ?? '') === '0')>No</option></select></div>
            <div class="col-12 general-indicator-field"><label class="form-label" for="general_indicator_id">Indicador a reportar</label><select class="form-select" name="indicador_id[]" id="general_indicator_id" multiple data-placeholder="Todos los indicadores" aria-describedby="general_indicators_help">@foreach($indicators as $indicator)<option value="{{ $indicator['id'] }}" @selected(in_array($indicator['id'], $filters['indicador_id'], true))>{{ $indicator['label'] }}</option>@endforeach</select><small id="general_indicators_help" class="form-text text-muted">Use &ldquo;Seleccionar todos los indicadores&rdquo; y quite los que no necesite. Sin selecci&oacute;n se incluyen todos.</small></div>

            <div class="col-12 d-flex flex-wrap justify-content-end gap-2 pt-2">
                <a class="btn btn-light" href="{{ url()->current() }}"><i class="ri-refresh-line me-1"></i>Limpiar</a>
                <button class="btn btn-primary" type="submit"><i class="ri-filter-3-line me-1"></i>Aplicar filtros</button>
            </div>
        </form>
    </div>
</section>

<div class="row general-kpis">
    @foreach([
        ['Personas atendidas', $summary['beneficiaries'], 'ri-group-line', 'primary'],
        ['NNA Mujeres', $summary['women_under_18'], 'ri-user-heart-line', 'success'],
        ['NNA Hombres', $summary['men_under_18'], 'ri-user-line', 'indigo'],
        ['Mujeres adultas', $summary['women_adults'], 'ri-women-line', 'danger'],
        ['Hombres adultos', $summary['men_adults'], 'ri-men-line', 'primary'],
    ] as [$label, $value, $icon, $tone])
    <div class="col-xl col-md-4 col-sm-6"><article class="card general-kpi-card"><div class="card-body"><div><p>{!! $label !!}</p><strong>{!! $value !!}</strong></div><span class="general-kpi-icon tone-{{ $tone }}"><i class="{{ $icon }}"></i></span></div></article></div>
    @endforeach
</div>




