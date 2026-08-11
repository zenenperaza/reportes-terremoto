@extends('layouts.app')

@php
    $editing = isset($report);
    $initialBeneficiaries = $editing ? $report->beneficiaries : collect();
@endphp

@section('title', ($editing ? 'Editar registro #'.$report->id : 'Nuevo registro').' | Respuesta ASONACOP')

@section('content')
    @php($nameParts = preg_split('/\s+/', trim($user->name), 2))
    <section class="page-heading compact-heading">
        <div>
            <p class="eyebrow">Formulario de respuesta</p>
            <h1>{{ $editing ? 'Editar actividad' : 'Registrar actividad' }}</h1>
            <p class="muted">{{ $editing ? 'Modifique la información necesaria y guarde los cambios del registro.' : 'Cada clic en “Guardar beneficiario” registra inmediatamente la persona en la base de datos.' }}</p>
        </div>
    </section>

    <form enctype="multipart/form-data" class="report-form" id="report-form"
        data-beneficiary-url="{{ route('beneficiaries.store') }}" data-location-reverse-url="{{ route('locations.reverse') }}"
        @if($editing) data-report-id="{{ $report->id }}" data-report-update-url="{{ route('reports.update', $report) }}" @endif
        novalidate>
        @csrf
        <section class="form-section">
            <div class="section-heading"><span>1</span>
                <div>
                    <h2>Actividad</h2>
                    <p>{{ $editing ? 'Puede modificar estos datos sin crear un registro nuevo.' : 'Si cambia cualquiera de estos encabezados, el próximo beneficiario iniciará un nuevo registro.' }}</p>
                </div>
            </div>
            <div class="form-grid ">
                <label>Proyecto *<select name="proyecto_id" id="proyecto_id" required>
                        <option value="">Seleccione un proyecto</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected((string) $selectedProjectId === (string) $project->id)>{{ $project->codigo }} — {{ $project->descripcion }}</option>
                        @endforeach
                    </select>
                </label><br>
                <label class="indicator-select-field">Seleccione indicador *<select name="indicador_proyecto_id" id="indicador_proyecto_id" required>
                        <option value="">Seleccione primero el proyecto</option>
                    </select><small class="indicator-select-help">Escriba para buscar por la descripción del indicador.</small><small id="selected-indicator-description" class="selected-indicator-description" hidden></small>
                </label><br>
                <label>Actividad a reportar *<select name="actividad_indicador_id" id="actividad_indicador_id" required>
                        <option value="">Seleccione primero el indicador</option>
                    </select>
                </label>
                <label class="span-two" id="report-services-field" hidden>Servicios *
                    <select name="servicio_actividad_ids[]" id="servicio_actividad_ids" multiple></select>
                    <small class="muted">Puede seleccionar uno o varios servicios.</small>
                </label>
                <label class="span-two">Detalles adicionales de la actividad
                    <textarea name="activity_details" rows="4" maxlength="300"
                        placeholder="Cantidades entregadas, temas de capacitación, logros o detalles relevantes.">{{ old('activity_details', $editing ? $report->activity_details : null) }}</textarea>
                </label>
            </div>
        </section>

        <section class="form-section">
            <div class="section-heading"><span>2</span>
                <div>
                    <h2>Quién implementa</h2>
                    <p>Estos datos se conservan para cada beneficiario del mismo registro.</p>
                </div>
            </div>
            <div class="form-grid three-cols">
                <label>Nombre *<input type="text" name="reporter_first_name"
                        value="{{ old('reporter_first_name', $editing ? $report->reporter_first_name : ($nameParts[0] ?? '')) }}" required></label>
                <label>Apellido *<input type="text" name="reporter_last_name"
                        value="{{ old('reporter_last_name', $editing ? $report->reporter_last_name : ($nameParts[1] ?? '')) }}" required></label>
                <label>Correo electrónico *<input type="email" name="reporter_email"
                        value="{{ old('reporter_email', $editing ? $report->reporter_email : $user->email) }}" required></label>
            </div>
            <br>
            <div class="form-grid three-cols">
                <label>Fecha de atencion *<input type="date" name="report_date"
                        value="{{ old('report_date', $editing ? $report->report_date->format('Y-m-d') : today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}"
                        required></label>

                <label>Organización implementadora *
                    <select name="organization" id="organization" required>
                        <option value="">Seleccione una organización</option>
                        @foreach ($organizations as $organization)
                            <option value="{{ $organization }}" @selected(old('organization', $editing ? $report->organization : 'ASONACOP') === $organization)>{{ $organization }}</option>
                        @endforeach
                    </select>
                </label>
                <label id="other-organization-field" hidden>Especifique otra organización<input type="text"
                        name="other_organization" value="{{ old('other_organization', $editing ? $report->other_organization : null) }}"></label>
            </div>
        </section>

        <section class="form-section">
            <div class="section-heading"><span>3</span>
                <div>
                    <h2>Ubicación</h2>
                    <p>La ubicación administrativa se cargará automáticamente desde el lugar seleccionado.</p>
                </div>
            </div>
            <div class="form-grid">
                <label class="checkbox-label community-location-toggle">
                    <input type="checkbox" name="is_community_location" id="is_community_location" value="1"
                        @checked(old('is_community_location', $communityLocation))>
                    No es un centro o espacio de atención formal o provisional
                </label>
                <div id="formal-place-fields">
                <label>Nombre específico del lugar *
                    <select name="place_name" id="place_name" required>
                        <option value="">Seleccione el nombre del lugar</option>
                        @foreach ($placeNames as $placeName)
                            <option value="{{ $placeName->name }}" data-state-id="{{ $placeName->state_id }}"
                                data-state-name="{{ $placeName->state?->name }}"
                                data-municipality-id="{{ $placeName->municipality_id }}"
                                data-municipality-name="{{ $placeName->municipality?->name }}"
                                data-parish-id="{{ $placeName->parish_id }}"
                                data-parish-name="{{ $placeName->parish?->name }}"
                                data-installation-type="{{ $placeName->installation_type }}"
                                data-latitude="{{ $placeName->latitude }}"
                                data-longitude="{{ $placeName->longitude }}"
                                data-altitude="{{ $placeName->altitude }}"
                                data-gps-accuracy="{{ $placeName->gps_accuracy }}" @selected(old('place_name', $editing ? $report->place_name : null) === $placeName->name)>
                                {{ $placeName->name }}</option>
                        @endforeach
                    </select>
                    @if(auth()->user()->isAdministrator())
                        <small>¿No aparece? <a href="{{ route('place-names.index') }}" target="_blank" rel="noopener">Crear o
                                administrar nombres de lugares</a>.</small>
                    @endif
                    <small id="place-location-summary" class="place-location-summary" hidden></small>
                </label>
                </div>
                <div class="form-grid three-cols community-location-fields" id="community-location-fields" hidden>
                    <label>Estado *
                        <select id="community_state_id">
                            <option value="">Seleccione el estado</option>
                            @foreach($states as $stateOption)
                                <option value="{{ $stateOption->id }}" @selected(old('state_id', $editing ? $report->state_id : null) == $stateOption->id)>{{ $stateOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Municipio *
                        <select id="community_municipality_id">
                            <option value="">Seleccione el municipio</option>
                            @foreach($communityMunicipalities as $municipalityOption)
                                <option value="{{ $municipalityOption->id }}" @selected(old('municipality_id', $editing ? $report->municipality_id : null) == $municipalityOption->id)>{{ $municipalityOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Parroquia *
                        <select id="community_parish_id">
                            <option value="">Seleccione la parroquia</option>
                            @foreach($communityParishes as $parishOption)
                                <option value="{{ $parishOption->id }}"
                                    data-latitude="{{ $parishOption->latitude }}"
                                    data-longitude="{{ $parishOption->longitude }}"
                                    @selected(old('parish_id', $editing ? $report->parish_id : null) == $parishOption->id)>{{ $parishOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="community-location-summary span-two" id="community-location-summary" hidden>
                        <strong id="community-generated-name"></strong>
                        <span>Latitud: <b id="community-latitude"></b></span>
                        <span>Longitud: <b id="community-longitude"></b></span>
                    </div>
                </div>
            </div>
            <input type="hidden" name="state_id" id="state_id" value="{{ old('state_id', $editing ? $report->state_id : null) }}">
            <input type="hidden" name="municipality_id" id="municipality_id" value="{{ old('municipality_id', $editing ? $report->municipality_id : null) }}">
            <input type="hidden" name="parish_id" id="parish_id" value="{{ old('parish_id', $editing ? $report->parish_id : null) }}">
            <input type="hidden" name="installation_type" id="installation_type" value="{{ old('installation_type', $editing ? $report->installation_type : null) }}">
            <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $editing ? $report->latitude : null) }}">
            <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $editing ? $report->longitude : null) }}">
            <input type="hidden" name="altitude" id="altitude" value="{{ old('altitude', $editing ? $report->altitude : null) }}">
            <input type="hidden" name="gps_accuracy" id="gps_accuracy" value="{{ old('gps_accuracy', $editing ? $report->gps_accuracy : null) }}">
        </section>

        <section class="form-section">
            <div class="section-heading"><span>4</span>
                <div>
                    <h2>Beneficiarios</h2>
                    <p>Complete una persona y guárdela. Los campos de esta sección se limpiarán, pero los encabezados
                        permanecerán.</p>
                </div>
            </div>
            <fieldset class="beneficiary-entry">
                <legend id="beneficiary-entry-title">Registrar beneficiario</legend>
                <div class="beneficiary-voice" id="beneficiary-voice">
                    <div class="beneficiary-voice-heading">
                        <div>
                            <strong>Dictado rápido</strong>
                            <p>Diga los datos de la persona en una sola frase. Podrá revisarlos antes de guardar.</p>
                        </div>
                        <button class="button button-secondary beneficiary-voice-button" type="button"
                            id="beneficiary-voice-toggle" aria-pressed="false">
                            <span aria-hidden="true">🎙</span> Dictar beneficiario
                        </button>
                    </div>
                    <div class="beneficiary-voice-transcript" id="beneficiary-voice-panel" hidden>
                        <label for="beneficiary-voice-text">Transcripción</label>
                        <textarea id="beneficiary-voice-text" rows="3"
                            placeholder="Ejemplo: María González, 34 años, mujer, cédula 18 456 782, teléfono 0414 123 4567, sin discapacidad, no indígena, lactante."></textarea>
                        <div class="beneficiary-voice-actions">
                            <button class="button button-secondary" type="button" id="beneficiary-voice-apply">Completar campos</button>
                            <button class="button button-ghost" type="button" id="beneficiary-voice-clear">Limpiar dictado</button>
                        </div>
                        <p class="beneficiary-voice-status" id="beneficiary-voice-status" role="status"></p>
                    </div>
                    <p class="beneficiary-voice-unavailable" id="beneficiary-voice-unavailable" hidden>
                        El reconocimiento de voz no está disponible en este navegador. Puede escribir o pegar la frase en la transcripción.
                    </p>
                </div>
                <p class="beneficiary-voice-example"><strong>Ejemplo:</strong> María González, 34 años, mujer, cédula 18 456 782, teléfono 0414 123 4567, sin discapacidad, no indígena, lactante.</p>
                <p class="beneficiary-voice-example"><strong>Comando:</strong> diga “guardar beneficiario” para registrar la persona por voz.</p>
                <div class="form-grid beneficiary-form-grid">
                    <label>Nombre y apellido<input type="text" id="beneficiary_full_name"
                            maxlength="150" autocomplete="name" style="text-transform: uppercase"></label>
                    <label>Edad *<input type="number" id="beneficiary_age" min="0" max="120"
                            inputmode="numeric"></label>
                    <label>Sexo *<select id="beneficiary_sex">
                            <option value="">Seleccione</option>
                            @foreach ($beneficiaryOptions['sexes'] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Cédula<input type="text" id="beneficiary_national_id" maxlength="30"
                            inputmode="numeric"></label>
                    <label>Teléfono<input type="text" id="beneficiary_phone" maxlength="30" inputmode="tel"></label>
                    <label>Discapacidad<select id="beneficiary_disability">
                            @foreach ($beneficiaryOptions['disabilities'] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Indígena / etnia<select id="beneficiary_ethnicity">
                            @foreach ($beneficiaryOptions['ethnicities'] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label id="beneficiary-pregnant-lactating-field">Embarazada o lactante<select
                            id="beneficiary_pregnant_lactating">
                            @foreach ($beneficiaryOptions['pregnant_lactating'] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select></label>
                </div>
                <div class="beneficiary-recurrence-field"><label>Recurrente *<select id="beneficiary_is_recurrent">
                            <option value="0" selected>No</option>
                            <option value="1">Sí</option>
                        </select></label>
                    <p class="muted">Indique esta condición de forma independiente, considerando la alerta de posibles
                        coincidencias.</p>
                </div>
                <div class="beneficiary-entry-actions">
                    <p id="beneficiary-entry-error" class="field-error" hidden></p>
                    <p id="beneficiary-entry-success" class="field-success" hidden></p><button
                        class="button button-secondary" type="button" id="save-beneficiary">Guardar
                        beneficiario</button><button class="button button-ghost" type="button"
                        id="cancel-beneficiary-edit" hidden>Cancelar edición</button>
                </div>
            </fieldset>

            <div class="beneficiary-list-card" @if($editing) hidden @endif>
                <div class="card-heading">
                    <div>
                        <h3>Beneficiarios guardados</h3>
                        <p class="muted">Cada fila ya está registrada en la base de datos.</p>
                    </div><strong class="beneficiary-number" id="beneficiary-total">0</strong>
                </div>
                <p id="beneficiary-empty" class="muted">Aún no ha guardado beneficiarios.</p>
                <div class="table-wrap" id="beneficiary-table-wrap" hidden>
                    <table class="beneficiary-table">
                        <thead>
                            <tr>
                                <th>Nombre y apellido</th>
                                <th>Edad</th>
                                <th>Sexo</th>
                                <th>Cédula</th>
                                <th>Teléfono</th>
                                <th>Discapacidad</th>
                                <th>Indígena</th>
                                <th>Emb./lact.</th>
                                <th>Recurrente</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="beneficiary-list"></tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="form-section">
            <div class="section-heading"><span>3</span>
                <div>
                    <h2>Grupos con necesidades específicas</h2>
                    <p>Se calculan automáticamente cada vez que guarda, edita o elimina un beneficiario.</p>
                </div>
            </div>
            <div class="beneficiary-summary">
                <div><span>Personas con discapacidad</span><strong id="summary-disability">0</strong></div>
                <div><span>Población indígena</span><strong id="summary-ethnicity">0</strong></div>
                <div><span>Embarazadas o en lactancia</span><strong id="summary-pregnancy">0</strong></div>
                <div><span>Beneficiarios recurrentes</span><strong id="summary-recurrent">0</strong></div>
                <div><span>Niños (menores de 18)</span><strong id="summary-boys">0</strong></div>
                <div><span>Niñas (menores de 18)</span><strong id="summary-girls">0</strong></div>
                <div><span>Hombres (18 años o más)</span><strong id="summary-men">0</strong></div>
                <div><span>Mujeres (18 años o más)</span><strong id="summary-women">0</strong></div>
            </div>
        </section>

        <div class="form-actions"><a class="button button-ghost" href="{{ $editing ? route('reports.show', $report) : route('dashboard') }}">Cancelar</a>
            @if($editing)<button class="button button-primary" type="button" id="save-report-changes">Guardar cambios del registro</button>@endif
            <a class="button button-secondary" id="current-report-link" href="{{ $editing ? route('reports.show', $report) : '#' }}" @if(!$editing) hidden @endif>Ver registro guardado</a>
        </div>
    </form>

@endsection

@push('scripts')
    <script>
        const select = (id) => document.getElementById(id);
        const setOptions = (element, items, placeholder, selected = '') => {
            element.innerHTML = `<option value="">${placeholder}</option>` + items.map(item =>
                `<option value="${item.id}" ${String(item.id) === String(selected) ? 'selected' : ''}>${item.name || item.title}</option>`
                ).join('');
        };
        const loadOptions = async (element, url, placeholder, selected = '') => {
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json'
                }
            });
            setOptions(element, await response.json(), placeholder, selected);
        };
        const form = select('report-form'),
            state = select('state_id'),
            municipality = select('municipality_id'),
            parish = select('parish_id'),
            project = select('proyecto_id'),
            activity = select('indicador_proyecto_id'),
            indicatorDescription = select('selected-indicator-description'),
            reportedActivity = select('actividad_indicador_id'),
            services = select('servicio_actividad_ids'),
            servicesField = select('report-services-field');
        const projectIndicators = @json($projectIndicatorOptions);
        const initialIndicator = @json(old('indicador_proyecto_id', $editing ? $report->indicador_proyecto_id : null));
        const initialActivity = @json(old('actividad_indicador_id', $editing ? $report->actividad_indicador_id : null));
        const initialServices = @json(old('servicio_actividad_ids', $editing ? $report->serviciosActividad->pluck('id')->all() : []));
        const selectedIndicator = () => (projectIndicators[project.value] || []).find(item => String(item.id) === String(activity.value));
        const syncIndicatorDescription = () => {
            const description = selectedIndicator()?.title || '';
            indicatorDescription.textContent = description;
            indicatorDescription.hidden = description === '';
        };
        const selectedProjectActivity = () => (selectedIndicator()?.activities || []).find(item => String(item.id) === String(reportedActivity.value));
        const syncServices = (selected = []) => {
            const items = selectedProjectActivity()?.services || [];
            const selectedIds = selected.map(String);
            services.innerHTML = items.map(item => `<option value="${item.id}" ${selectedIds.includes(String(item.id)) ? 'selected' : ''}>${item.title}</option>`).join('');
            servicesField.hidden = items.length === 0;
            services.required = items.length > 0;
            if (window.jQuery && jQuery.fn.select2) jQuery(services).trigger('change.select2');
        };
        const syncIndicatorActivities = (selected = '', selectedServices = []) => {
            syncIndicatorDescription();
            setOptions(reportedActivity, selectedIndicator()?.activities || [], activity.value ? 'Seleccione una actividad' : 'Seleccione primero el indicador', selected);
            syncServices(selectedServices);
        };
        const syncProjectIndicators = (selected = '', selectedActivity = '', selectedServices = []) => {
            setOptions(activity, projectIndicators[project.value] || [], project.value ? 'Seleccione un indicador' : 'Seleccione primero el proyecto', selected);
            if (window.jQuery && jQuery.fn.select2) jQuery(activity).trigger('change.select2');
            syncIndicatorActivities(selectedActivity, selectedServices);
        };
        project.addEventListener('change', () => syncProjectIndicators());
        reportedActivity.addEventListener('change', () => syncServices());
        if (window.jQuery && jQuery.fn.select2) {
            jQuery(activity).select2({
                width: '100%',
                placeholder: 'Seleccione un indicador',
                dropdownCssClass: 'indicator-select2-dropdown',
                language: {noResults: () => 'No se encontraron indicadores'},
            });
            jQuery(activity).on('change', () => syncIndicatorActivities());
            jQuery(services).select2({width: '100%', placeholder: 'Seleccione uno o varios servicios'});
        } else {
            activity.addEventListener('change', () => syncIndicatorActivities());
        }
        syncProjectIndicators(initialIndicator, initialActivity, initialServices);
        const placeName = select('place_name'),
            installationType = select('installation_type'),
            placeLocationSummary = select('place-location-summary');
        const syncPlaceLocation = () => {
            const option = placeName.selectedOptions[0];
            state.value = option?.dataset.stateId || '';
            municipality.value = option?.dataset.municipalityId || '';
            parish.value = option?.dataset.parishId || '';
            installationType.value = option?.dataset.installationType || '';
            select('latitude').value = option?.dataset.latitude || '';
            select('longitude').value = option?.dataset.longitude || '';
            select('altitude').value = option?.dataset.altitude || '';
            select('gps_accuracy').value = option?.dataset.gpsAccuracy || '';
            placeLocationSummary.hidden = !placeName.value;
            placeLocationSummary.textContent = placeName.value
                ? `Estado: ${option.dataset.stateName} · Municipio: ${option.dataset.municipalityName} · Parroquia: ${option.dataset.parishName}`
                : '';
        };
        placeName.addEventListener('change', syncPlaceLocation);
        if (placeName.value) syncPlaceLocation();
        const communityLocationToggle = select('is_community_location'),
            formalPlaceFields = select('formal-place-fields'),
            communityLocationFields = select('community-location-fields'),
            communityState = select('community_state_id'),
            communityMunicipality = select('community_municipality_id'),
            communityParish = select('community_parish_id'),
            communityLocationSummary = select('community-location-summary'),
            communityGeneratedName = select('community-generated-name'),
            communityLatitude = select('community-latitude'),
            communityLongitude = select('community-longitude');
        const setCommunityOptions = (element, items, placeholder) => {
            element.innerHTML = `<option value="">${placeholder}</option>` + items.map(item =>
                `<option value="${item.id}" data-latitude="${item.latitude ?? ''}" data-longitude="${item.longitude ?? ''}">${item.name}</option>`
            ).join('');
        };
        const clearCommunityLocation = () => {
            state.value = '';
            municipality.value = '';
            parish.value = '';
            select('installation_type').value = '';
            select('latitude').value = '';
            select('longitude').value = '';
            select('altitude').value = '';
            select('gps_accuracy').value = '';
            placeName.querySelector('option[data-community-generated]')?.remove();
            placeName.value = '';
            communityLocationSummary.hidden = true;
            communityGeneratedName.textContent = '';
            communityLatitude.textContent = '';
            communityLongitude.textContent = '';
        };
        const syncCommunityLocation = () => {
            clearCommunityLocation();
            const option = communityParish.selectedOptions[0];
            if (!communityState.value || !communityMunicipality.value || !communityParish.value) return;
            const generatedName = `Comunidad ${option.textContent.trim()}`;
            const generatedOption = document.createElement('option');
            generatedOption.value = generatedName;
            generatedOption.textContent = generatedName;
            generatedOption.dataset.communityGenerated = '1';
            placeName.appendChild(generatedOption);
            placeName.value = generatedName;
            state.value = communityState.value;
            municipality.value = communityMunicipality.value;
            parish.value = communityParish.value;
            select('installation_type').value = 'Comunidad / Espacio Comunitario';
            select('latitude').value = option.dataset.latitude || '';
            select('longitude').value = option.dataset.longitude || '';
            communityLocationSummary.hidden = false;
            communityGeneratedName.textContent = `Nombre del lugar: ${generatedName}`;
            communityLatitude.textContent = option.dataset.latitude || 'No definida';
            communityLongitude.textContent = option.dataset.longitude || 'No definida';
        };
        const syncCommunityMode = () => {
            const enabled = communityLocationToggle.checked;
            formalPlaceFields.hidden = enabled;
            communityLocationFields.hidden = !enabled;
            placeName.required = !enabled;
            [communityState, communityMunicipality, communityParish].forEach(element => element.required = enabled);
            if (enabled) syncCommunityLocation();
            else {
                clearCommunityLocation();
                syncPlaceLocation();
            }
        };
        communityState.addEventListener('change', async () => {
            clearCommunityLocation();
            setCommunityOptions(communityMunicipality, [], 'Cargando municipios');
            setCommunityOptions(communityParish, [], 'Seleccione la parroquia');
            if (communityState.value) {
                const response = await fetch(`/ubicaciones/estados/${communityState.value}/municipios`, {headers: {'Accept': 'application/json'}});
                setCommunityOptions(communityMunicipality, await response.json(), 'Seleccione el municipio');
            }
        });
        communityMunicipality.addEventListener('change', async () => {
            clearCommunityLocation();
            setCommunityOptions(communityParish, [], 'Cargando parroquias');
            if (communityMunicipality.value) {
                const response = await fetch(`/ubicaciones/municipios/${communityMunicipality.value}/parroquias`, {headers: {'Accept': 'application/json'}});
                setCommunityOptions(communityParish, await response.json(), 'Seleccione la parroquia');
            }
        });
        communityParish.addEventListener('change', syncCommunityLocation);
        communityLocationToggle.addEventListener('change', syncCommunityMode);
        syncCommunityMode();
        const validateCoordinates = async () => true;

        const beneficiaryFields = ['full_name', 'age', 'sex', 'national_id', 'phone', 'disability', 'ethnicity',
            'pregnant_lactating', 'is_recurrent'
        ];
        const beneficiaryInputs = Object.fromEntries(beneficiaryFields.map(field => [field, select(
            `beneficiary_${field}`)]));
        const pregnantLactatingField = select('beneficiary-pregnant-lactating-field');
        const syncPregnantLactatingField = () => {
            const isMan = beneficiaryInputs.sex.value === 'Hombre';
            pregnantLactatingField.hidden = isMan;
            if (isMan) beneficiaryInputs.pregnant_lactating.value = 'Ninguna';
        };
        const entryError = select('beneficiary-entry-error'),
            entrySuccess = select('beneficiary-entry-success'),
            beneficiaryList = select('beneficiary-list'),
            beneficiaryEmpty = select('beneficiary-empty'),
            beneficiaryTable = select('beneficiary-table-wrap'),
            saveButton = select('save-beneficiary');
        const headerFields = ['report_date', 'reporter_first_name', 'reporter_last_name', 'reporter_email', 'organization',
            'other_organization', 'state_id', 'municipality_id', 'parish_id', 'installation_type', 'place_name',
            'proyecto_id', 'indicador_proyecto_id', 'actividad_indicador_id', 'servicio_actividad_ids[]'
        ];
        let beneficiaries = @json($initialBeneficiaries),
            activeReportId = form.dataset.reportId || null,
            activeHeaderSignature = null,
            beneficiaryEditId = null,
            isSaving = false;
        let currentSummary = {
            total: {{ $editing ? $report->total_beneficiaries : 0 }},
            people_with_disabilities: {{ $editing ? $report->people_with_disabilities : 0 }},
            indigenous_people: {{ $editing ? $report->indigenous_people : 0 }},
            pregnant_or_lactating_women: {{ $editing ? $report->pregnant_or_lactating_women : 0 }}
        };
        const inputValue = field => beneficiaryInputs[field].value.trim();
        const beneficiaryRecord = () => Object.fromEntries(beneficiaryFields.map(field => [field, inputValue(field)]));
        const headerSignature = () => JSON.stringify(Object.fromEntries(headerFields.map(field => {
            if (field === 'servicio_actividad_ids[]') return [field, Array.from(services.selectedOptions).map(option => option.value).sort()];
            return [field, form.elements[field]?.value.trim() || ''];
        })));
        if (activeReportId) activeHeaderSignature = headerSignature();
        const setMessage = (element, message = '') => {
            element.textContent = message;
            element.hidden = !message;
        };
        const voiceToggle = select('beneficiary-voice-toggle'),
            voicePanel = select('beneficiary-voice-panel'),
            voiceText = select('beneficiary-voice-text'),
            voiceStatus = select('beneficiary-voice-status'),
            SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        let voiceRecognition = null,
            voiceSessionActive = false,
            voiceSessionBase = '';
        const normalizeVoiceText = value => value.toLocaleLowerCase('es-VE').normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, ' ').trim();
        const spokenDigits = value => {
            const words = {cero:'0', uno:'1', un:'1', dos:'2', tres:'3', cuatro:'4', cinco:'5', seis:'6', siete:'7', ocho:'8', nueve:'9'};
            return normalizeVoiceText(value).split(/\s+/).map(part => words[part] ?? part.replace(/\D/g, '')).join('');
        };
        const selectMatch = (input, aliases) => [...input.options].find(option => {
            const value = normalizeVoiceText(option.value);
            return value && aliases.some(alias => value.includes(normalizeVoiceText(alias)));
        })?.value || '';
        const markVoiceField = (field, recognized) => {
            beneficiaryInputs[field].classList.toggle('voice-recognized', recognized);
            beneficiaryInputs[field].classList.toggle('voice-review', !recognized && ['age', 'sex'].includes(field));
        };
        const applyVoiceTranscript = () => {
            const raw = voiceText.value.trim(), text = normalizeVoiceText(raw), recognized = new Set();
            if (!text) {
                voiceStatus.textContent = 'Dicte o escriba primero los datos del beneficiario.';
                voiceText.focus();
                return;
            }
            const age = text.match(/(?:edad\s*)?(\d{1,3})\s*(?:anos|ano)/);
            if (age && Number(age[1]) <= 120) { beneficiaryInputs.age.value = age[1]; recognized.add('age'); }
            if (/\b(mujer|femenin[oa]|nina|señora)\b/.test(text)) {
                beneficiaryInputs.sex.value = 'Mujer'; recognized.add('sex');
            } else if (/\b(hombre|masculin[oa]|nino|señor)\b/.test(text)) {
                beneficiaryInputs.sex.value = 'Hombre'; recognized.add('sex');
            }
            const nationalId = text.match(/(?:cedula|identidad|\bci\b)\s*(?:numero)?\s*([\d\s.-]{5,})/);
            if (nationalId) { beneficiaryInputs.national_id.value = spokenDigits(nationalId[1]); recognized.add('national_id'); }
            const phone = text.match(/(?:telefono|celular|movil)\s*(?:numero)?\s*([\d\s.-]{7,})/);
            if (phone) { beneficiaryInputs.phone.value = spokenDigits(phone[1]); recognized.add('phone'); }
            const explicitName = raw.match(/nombre(?:\s+y\s+apellido)?\s*(?:es)?\s*(.+?)(?=,|;|\bedad\b|\b\d{1,3}\s*años?\b|$)/i);
            const leadingName = raw.split(/\s*(?:,|;|\bedad\b|\b\d{1,3}\s*años?\b|\bmujer\b|\bhombre\b|\bcédula\b|\btelefono\b)/i)[0]
                .replace(/^(beneficiari[oa]|persona)\s+/i, '').trim();
            const name = (explicitName?.[1] || leadingName).replace(/[.,;:]$/, '').trim();
            if (name && name.split(/\s+/).length >= 2) {
                beneficiaryInputs.full_name.value = name.toLocaleUpperCase('es-VE');
                recognized.add('full_name');
            }
            if (/\b(sin discapacidad|ninguna discapacidad)\b/.test(text)) {
                beneficiaryInputs.disability.value = 'Ninguna'; recognized.add('disability');
            } else {
                const disability = ['fisica', 'motora', 'sensorial', 'auditiva', 'sorda', 'visual', 'ciega', 'intelectual', 'psiquica']
                    .find(alias => text.includes(alias));
                if (disability) {
                    const aliases = disability === 'sorda' ? ['auditiva'] : disability === 'ciega' ? ['visual'] : [disability];
                    const value = selectMatch(beneficiaryInputs.disability, aliases);
                    if (value) { beneficiaryInputs.disability.value = value; recognized.add('disability'); }
                }
            }
            if (/\b(no indigena|sin etnia|ninguna etnia)\b/.test(text)) {
                beneficiaryInputs.ethnicity.value = 'Ninguna'; recognized.add('ethnicity');
            } else {
                const ethnicity = [...beneficiaryInputs.ethnicity.options].find(option => option.value !== 'Ninguna' &&
                    text.includes(normalizeVoiceText(option.value)));
                if (ethnicity) { beneficiaryInputs.ethnicity.value = ethnicity.value; recognized.add('ethnicity'); }
            }
            if (/\b(embarazada|gestante|lactante|amamantando)\b/.test(text) && !/\b(no embarazada|no lactante)\b/.test(text)) {
                beneficiaryInputs.pregnant_lactating.value = 'Sí'; recognized.add('pregnant_lactating');
            } else if (/\b(no embarazada|no lactante|ni embarazada ni lactante)\b/.test(text)) {
                beneficiaryInputs.pregnant_lactating.value = 'No'; recognized.add('pregnant_lactating');
            }
            if (/\b(recurrente|ya atendid[oa]|atendid[oa] anteriormente)\b/.test(text) && !/\bno recurrente\b/.test(text)) {
                beneficiaryInputs.is_recurrent.value = '1'; recognized.add('is_recurrent');
            }
            syncPregnantLactatingField();
            beneficiaryFields.forEach(field => markVoiceField(field, recognized.has(field)));
            voiceStatus.textContent = `Se completaron ${recognized.size} campos. Revise especialmente cédula, teléfono y los campos amarillos antes de guardar.`;
            const missing = ['age', 'sex'].find(field => !recognized.has(field));
            (missing ? beneficiaryInputs[missing] : beneficiaryInputs.full_name).focus();
        };
        if (SpeechRecognition) {
            voiceRecognition = new SpeechRecognition();
            voiceRecognition.lang = 'es-VE';
            voiceRecognition.interimResults = true;
            voiceRecognition.continuous = true;
            voiceRecognition.onstart = () => {
                voicePanel.hidden = false;
                voiceToggle.classList.add('is-listening');
                voiceToggle.setAttribute('aria-pressed', 'true');
                voiceToggle.innerHTML = '<span aria-hidden="true">■</span> Detener dictado';
                voiceStatus.textContent = 'Escuchando… diga los datos de la persona.';
            };
            voiceRecognition.onresult = event => {
                const latestResult = event.results[event.results.length - 1];
                const currentTranscript = [...event.results].map(result => result[0].transcript).join(' ').trim();
                voiceText.value = [voiceSessionBase, currentTranscript].filter(Boolean).join(' ').trim();
                applyVoiceTranscript();
                const latestText = normalizeVoiceText(latestResult[0].transcript);
                if (latestResult.isFinal && /\b(?:guardar|guarda)(?:\s+(?:el\s+)?beneficiari[oa])?[.!]?$/.test(latestText)) {
                    voiceSessionActive = false;
                    voiceRecognition.stop();
                    voiceStatus.textContent = 'Comando reconocido. Guardando beneficiario…';
                    void saveBeneficiary();
                    return;
                }
                voiceStatus.textContent += ' Continúe hablando o presione “Detener dictado” para finalizar.';
            };
            voiceRecognition.onerror = event => {
                const errors = {'not-allowed':'No se concedió permiso para usar el micrófono.','no-speech':'No se detectó voz. Inténtelo nuevamente.','audio-capture':'No se encontró un micrófono disponible.'};
                voiceStatus.textContent = errors[event.error] || 'No fue posible reconocer la voz. También puede escribir la frase.';
                if (['not-allowed', 'audio-capture', 'service-not-allowed'].includes(event.error)) voiceSessionActive = false;
            };
            voiceRecognition.onend = () => {
                if (voiceSessionActive) {
                    voiceSessionBase = voiceText.value.trim();
                    window.setTimeout(() => {
                        if (voiceSessionActive) {
                            try { voiceRecognition.start(); } catch (error) { voiceSessionActive = false; }
                        }
                    }, 250);
                    return;
                }
                voiceToggle.classList.remove('is-listening');
                voiceToggle.setAttribute('aria-pressed', 'false');
                voiceToggle.innerHTML = '<span aria-hidden="true">🎙</span> Dictar beneficiario';
            };
        } else {
            select('beneficiary-voice-unavailable').hidden = false;
            voiceToggle.textContent = 'Escribir datos rápidos';
        }
        voiceToggle.addEventListener('click', () => {
            voicePanel.hidden = false;
            if (!voiceRecognition) return voiceText.focus();
            if (voiceSessionActive) {
                voiceSessionActive = false;
                voiceRecognition.stop();
                voiceStatus.textContent = 'Dictado finalizado. Revise los datos antes de guardar.';
            } else {
                voiceSessionActive = true;
                voiceSessionBase = voiceText.value.trim();
                voiceRecognition.start();
            }
        });
        select('beneficiary-voice-apply').addEventListener('click', applyVoiceTranscript);
        select('beneficiary-voice-clear').addEventListener('click', () => {
            voiceText.value = ''; voiceStatus.textContent = '';
            beneficiaryFields.forEach(field => beneficiaryInputs[field].classList.remove('voice-recognized', 'voice-review'));
            voiceText.focus();
        });
        const clearBeneficiaryEntry = () => {
            beneficiaryFields.forEach(field => beneficiaryInputs[field].value = '');
            beneficiaryInputs.disability.value = 'Ninguna';
            beneficiaryInputs.ethnicity.value = 'Ninguna';
            beneficiaryInputs.pregnant_lactating.value = 'Ninguna';
            beneficiaryInputs.is_recurrent.value = '0';
            syncPregnantLactatingField();
            beneficiaryEditId = null;
            select('beneficiary-entry-title').textContent = 'Registrar beneficiario';
            saveButton.textContent = 'Guardar beneficiario';
            select('cancel-beneficiary-edit').hidden = true;
            recurrenceWarning.hidden = true;
            voiceText.value = '';
            voiceStatus.textContent = '';
            beneficiaryFields.forEach(field => beneficiaryInputs[field].classList.remove('voice-recognized', 'voice-review'));
        };
        const requiredHeaderFields = [
            ['report_date', 'fecha de registro'],
            ['reporter_first_name', 'nombre de quien registra'],
            ['reporter_last_name', 'apellido de quien registra'],
            ['reporter_email', 'correo electrónico'],
            ['organization', 'organización'],
            ['state_id', 'estado'],
            ['municipality_id', 'municipio'],
            ['parish_id', 'parroquia'],
            ['installation_type', 'tipo de instalación'],
            ['place_name', 'nombre del lugar'],
            ['proyecto_id', 'proyecto'],
            ['indicador_proyecto_id', 'indicador'],
            ['actividad_indicador_id', 'actividad a reportar']
        ];
        const ensureReportContext = () => {
            const missing = requiredHeaderFields.find(([field]) => !form.elements[field].value.trim());
            if (!missing && (!services.required || services.selectedOptions.length > 0)) return true;
            if (!missing) {
                setMessage(entryError, 'Antes de guardar, seleccione al menos un servicio.');
                services.focus();
                return false;
            }
            setMessage(entryError, `Antes de guardar, complete ${missing[1]}.`);
            const communityField = {
                state_id: communityState,
                municipality_id: communityMunicipality,
                parish_id: communityParish,
                place_name: communityParish
            }[missing[0]];
            (communityLocationToggle.checked && communityField ? communityField : form.elements[missing[0]]).focus();
            return false;
        };
        const beneficiaryValidationMessage = beneficiary => {
            const labels = {
                age: 'edad',
                sex: 'sexo',
                is_recurrent: 'recurrente'
            };
            const missing = Object.keys(labels).filter(field => beneficiary[field] === '');
            const errors = missing.length ? [
                `Complete los campos obligatorios: ${missing.map(field => labels[field]).join(', ')}.`
            ] : [];
            if (beneficiary.age !== '' && (!Number.isInteger(Number(beneficiary.age)) || Number(beneficiary.age) < 0 ||
                    Number(beneficiary.age) > 120)) errors.push('Indique una edad válida entre 0 y 120 años.');
            return errors.join(' ');
        };
        const updateSummary = summary => {
            currentSummary = summary;
            select('beneficiary-total').textContent = Number(summary.total || 0).toLocaleString('es-VE');
            select('summary-disability').textContent = summary.people_with_disabilities || 0;
            select('summary-ethnicity').textContent = summary.indigenous_people || 0;
            select('summary-pregnancy').textContent = summary.pregnant_or_lactating_women || 0;
            select('summary-recurrent').textContent = beneficiaries.filter(item => Boolean(item.is_recurrent)).length;
            select('summary-boys').textContent = beneficiaries.filter(item => Number(item.age) < 18 && item.sex === 'Hombre').length;
            select('summary-girls').textContent = beneficiaries.filter(item => Number(item.age) < 18 && item.sex === 'Mujer').length;
            select('summary-men').textContent = beneficiaries.filter(item => Number(item.age) >= 18 && item.sex === 'Hombre').length;
            select('summary-women').textContent = beneficiaries.filter(item => Number(item.age) >= 18 && item.sex === 'Mujer').length;
        };

        function renderBeneficiaries() {
            beneficiaryList.replaceChildren();
            beneficiaries.forEach((beneficiary, index) => {
                const row = document.createElement('tr');
                [beneficiary.full_name || 'Sin nombre registrado', beneficiary.age, beneficiary.sex, beneficiary
                    .national_id || '—', beneficiary.phone || '—', beneficiary.disability || 'Ninguna',
                    beneficiary.ethnicity || 'Ninguna', beneficiary.pregnant_lactating || 'Ninguna',
                    beneficiary.is_recurrent ? 'Sí' : 'No'
                ].forEach(value => {
                    const cell = document.createElement('td');
                    cell.textContent = value;
                    row.appendChild(cell);
                });
                const actions = document.createElement('td');
                const edit = document.createElement('button');
                edit.type = 'button';
                edit.className = 'table-action';
                edit.dataset.beneficiaryId = beneficiary.id;
                edit.textContent = 'Editar';
                edit.addEventListener('click', () => {
                    beneficiaryFields.forEach(field => beneficiaryInputs[field].value = field ===
                        'is_recurrent' ? (beneficiary[field] ? '1' : '0') : String(beneficiary[field] ??
                            ''));
                    syncPregnantLactatingField();
                    beneficiaryEditId = beneficiary.id;
                    select('beneficiary-entry-title').textContent = `Editar beneficiario ${index + 1}`;
                    saveButton.textContent = 'Actualizar beneficiario';
                    select('cancel-beneficiary-edit').hidden = false;
                    select('beneficiary_full_name').focus();
                });
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'table-action danger-action';
                remove.textContent = 'Eliminar';
                remove.addEventListener('click', () => removeBeneficiary(beneficiary));
                actions.append(edit, remove);
                row.appendChild(actions);
                beneficiaryList.appendChild(row);
            });
            beneficiaryEmpty.hidden = beneficiaries.length > 0;
            beneficiaryTable.hidden = beneficiaries.length === 0;
            updateSummary(currentSummary);
        }
        const recurrenceWarning = document.createElement('p');
        recurrenceWarning.className = 'recurrence-warning';
        recurrenceWarning.hidden = true;
        recurrenceWarning.setAttribute('role', 'status');
        beneficiaryInputs.is_recurrent.closest('label').before(recurrenceWarning);
        let recurrenceCheck = 0;
        const checkPossibleRecurrence = async () => {
            const beneficiary = beneficiaryRecord(),
                hasFallback = beneficiary.full_name && beneficiary.age !== '' && beneficiary.sex,
                hasLocation = state.value && municipality.value && parish.value;
            if (!activity.value || (!beneficiary.national_id && (!hasFallback || !hasLocation))) {
                recurrenceWarning.hidden = true;
                return;
            }
            const currentCheck = ++recurrenceCheck;
            const params = new URLSearchParams({
                indicador_proyecto_id: activity.value,
                state_id: state.value,
                municipality_id: municipality.value,
                parish_id: parish.value,
                national_id: beneficiary.national_id,
                full_name: beneficiary.full_name,
                age: beneficiary.age,
                sex: beneficiary.sex,
                exclude_beneficiary_id: beneficiaryEditId || ''
            });
            try {
                const response = await fetch(`{{ route('beneficiaries.recurrence') }}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    }),
                    result = await response.json();
                if (currentCheck !== recurrenceCheck) return;
                if (result.matches > 0) {
                    recurrenceWarning.textContent =
                        `Aviso: se encontró ${result.matches} coincidencia(s) para esta actividad por nombre, edad, sexo, ubicación y actividad. A continuación indique si es un Beneficiario Recurrente.`;
                    recurrenceWarning.hidden = false;
                } else recurrenceWarning.hidden = true;
            } catch (_) {
                recurrenceWarning.hidden = true;
            }
        };
        const responseMessage = async response => {
            const payload = await response.json().catch(() => ({}));
            if (response.ok) return payload;
            const errors = payload.errors ? Object.values(payload.errors).flat()[0] : null;
            throw new Error(errors || payload.message || 'No se pudo guardar la información.');
        };
        const requestHeaders = {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        };
        async function saveBeneficiary() {
            if (isSaving || !ensureReportContext()) return;
            if (!await validateCoordinates()) {
                setMessage(entryError, 'Corrija las coordenadas GPS antes de guardar el beneficiario.');
                (latitudeInput.value.trim() ? longitudeInput : latitudeInput).focus();
                return;
            }
            const beneficiary = beneficiaryRecord();
            const validationMessage = beneficiaryValidationMessage(beneficiary);
            if (validationMessage) {
                setMessage(entryError, validationMessage);
                return;
            }
            const signature = headerSignature(),
                headersChanged = Boolean(activeReportId && activeHeaderSignature !== signature),
                createsNewReport = !activeReportId || headersChanged;
            if (form.dataset.reportUpdateUrl && headersChanged) {
                setMessage(entryError, 'Guarde primero los cambios del registro antes de guardar o editar beneficiarios.');
                return;
            }
            if (beneficiaryEditId && createsNewReport) {
                setMessage(entryError,
                'Para editar, restaure los encabezados con los que se guardó este beneficiario.');
                return;
            }
            let data;
            let url = form.dataset.beneficiaryUrl;
            if (beneficiaryEditId) {
                data = new FormData();
                beneficiaryFields.forEach(field => data.set(field, beneficiary[field]));
                url = `{{ url('/beneficiarios') }}/${beneficiaryEditId}`;
                data.set('_method', 'PUT');
            } else {
                data = new FormData(form);
                beneficiaryFields.forEach(field => data.set(`beneficiary[${field}]`, beneficiary[field]));
                if (!createsNewReport) data.set('report_id', activeReportId);
            }
            isSaving = true;
            saveButton.disabled = true;
            setMessage(entryError);
            setMessage(entrySuccess);
            try {
                const result = await responseMessage(await fetch(url, {
                    method: 'POST',
                    headers: requestHeaders,
                    body: data
                }));
                if (beneficiaryEditId) beneficiaries = beneficiaries.map(item => item.id === result.beneficiary.id ?
                    result.beneficiary : item);
                else beneficiaries = createsNewReport ? [result.beneficiary] : [...beneficiaries, result.beneficiary];
                if (result.report) {
                    activeReportId = result.report.id;
                    activeHeaderSignature = signature;
                    const link = select('current-report-link');
                    link.href = result.report.url;
                    link.hidden = false;
                }
                currentSummary = result.summary;
                renderBeneficiaries();
                clearBeneficiaryEntry();
                ['evidence_1', 'evidence_2', 'evidence_3'].forEach(field => {
                    if (form.elements[field]) form.elements[field].value = '';
                });
                setMessage(entrySuccess, createsNewReport ?
                    'Beneficiario guardado. Se creó un nuevo registro con estos encabezados.' : result.message);
            } catch (error) {
                setMessage(entryError, error.message);
            } finally {
                isSaving = false;
                saveButton.disabled = false;
            }
        }
        async function removeBeneficiary(beneficiary) {
            if (!confirm(`¿Eliminar a ${beneficiary.full_name || 'este beneficiario'}?`)) return;
            setMessage(entryError);
            setMessage(entrySuccess);
            try {
                const result = await responseMessage(await fetch(`{{ url('/beneficiarios') }}/${beneficiary.id}`, {
                    method: 'DELETE',
                    headers: requestHeaders
                }));
                beneficiaries = beneficiaries.filter(item => item.id !== beneficiary.id);
                currentSummary = result.summary;
                if (result.report_deleted) {
                    activeReportId = null;
                    activeHeaderSignature = null;
                    select('current-report-link').hidden = true;
                }
                renderBeneficiaries();
                setMessage(entrySuccess, result.message);
            } catch (error) {
                setMessage(entryError, error.message);
            }
        }
        saveButton.addEventListener('click', saveBeneficiary);
        select('cancel-beneficiary-edit').addEventListener('click', clearBeneficiaryEntry);
        form.addEventListener('submit', event => event.preventDefault());
        ['full_name', 'national_id'].forEach(field => beneficiaryInputs[field].addEventListener('blur',
            checkPossibleRecurrence));
        beneficiaryInputs.full_name.addEventListener('input', () => {
            const start = beneficiaryInputs.full_name.selectionStart;
            beneficiaryInputs.full_name.value = beneficiaryInputs.full_name.value.toLocaleUpperCase('es-VE');
            beneficiaryInputs.full_name.setSelectionRange(start, start);
        });
        beneficiaryInputs.age.addEventListener('change', checkPossibleRecurrence);
        beneficiaryInputs.sex.addEventListener('change', () => {
            syncPregnantLactatingField();
            checkPossibleRecurrence();
        });
        beneficiaryInputs.is_recurrent.addEventListener('focus', checkPossibleRecurrence);
        const organization = select('organization'),
            otherOrganization = select('other-organization-field');
        const syncOrganization = () => {
            const visible = organization.value === 'Otro Socio Implementador';
            otherOrganization.hidden = !visible;
            otherOrganization.querySelector('input').required = visible;
        };
        organization.addEventListener('change', syncOrganization);
        syncOrganization();
        syncPregnantLactatingField();
        renderBeneficiaries();
        const initialBeneficiaryEditId = @json($editing ? $editBeneficiaryId : null);
        if (initialBeneficiaryEditId) {
            const initialEditButton = beneficiaryList.querySelector(`[data-beneficiary-id="${initialBeneficiaryEditId}"]`);
            if (initialEditButton) {
                initialEditButton.click();
                select('beneficiary-entry-title').scrollIntoView({behavior: 'smooth', block: 'center'});
            }
        }

        const saveReportChanges = select('save-report-changes');
        if (saveReportChanges) saveReportChanges.addEventListener('click', async () => {
            if (isSaving || !ensureReportContext()) return;
            const invalidField = [...form.querySelectorAll('[name][required]')].find(field => !field.checkValidity());
            if (invalidField) {
                invalidField.reportValidity();
                return;
            }
            isSaving = true;
            saveReportChanges.disabled = true;
            setMessage(entryError);
            setMessage(entrySuccess);
            try {
                const data = new FormData(form);
                data.set('_method', 'PUT');
                const result = await responseMessage(await fetch(form.dataset.reportUpdateUrl, {
                    method: 'POST',
                    headers: requestHeaders,
                    body: data
                }));
                activeHeaderSignature = headerSignature();
                ['evidence_1', 'evidence_2', 'evidence_3'].forEach(field => {
                    if (form.elements[field]) form.elements[field].value = '';
                });
                setMessage(entrySuccess, result.message);
            } catch (error) {
                setMessage(entryError, error.message);
            } finally {
                isSaving = false;
                saveReportChanges.disabled = false;
            }
        });
    </script>
@endpush
