@extends('layouts.app')

@php
    $editing = isset($report);
    $continuing = $continuing ?? false;
    $contextOld = fn ($key, $default = null) => $continuing ? $default : old($key, $default);
    $editingBeneficiary = $editingBeneficiary ?? null;
    $initialBeneficiaries = $editingBeneficiary ? collect([$editingBeneficiary]) : ($editing ? $report->beneficiaries : collect());
    $nationalityOptions = collect($beneficiaryOptions['nationalities'])
        ->merge($initialBeneficiaries->pluck('nationality'))->push(old('beneficiary.nationality'))
        ->filter(fn ($value) => is_string($value) && $value !== '')->unique()->values();
    $caregiverRelationshipOptions = collect($beneficiaryOptions['caregiver_relationships'])
        ->merge($initialBeneficiaries->pluck('caregiver_relationship'))->push(old('beneficiary.caregiver_relationship'))
        ->filter(fn ($value) => is_string($value) && $value !== '')->unique()->values();
@endphp

@section('title', ($continuing ? 'Continuar registro #'.$report->id : ($editing ? 'Editar registro #'.$report->id : 'Nuevo registro')).' | SIA')

@section('content')
    @php($registrant = \App\Services\ReportRegistrant::fields($user, $editing ? $report : null))
    <section class="page-heading compact-heading">
        <div>
            <p class="eyebrow">Formulario de respuesta</p>
            <h1>{{ $continuing ? 'Continuar registro #'.$report->id : ($editingBeneficiary ? 'Editar beneficiario #'.$editingBeneficiary->id : ($editing ? 'Editar registro completo' : 'Registrar actividad')) }}</h1>
            <p class="muted">{{ $continuing ? 'Las nuevas personas se agregarán a este mismo listado. Los datos de la actividad se conservan sin cambios.' : ($editingBeneficiary ? 'Los cambios afectan solo a esta persona. Si cambia los datos compartidos, se separará del grupo sin modificar a los demás.' : ($editing ? 'Los cambios de proyecto, indicador y ubicación se aplican a todos los beneficiarios de este registro.' : 'Cada clic en “Guardar beneficiario” registra inmediatamente la persona en la base de datos.')) }}</p>
            @if(!$editing)
                @can('solo ver registros')<p class="muted">¿Ya comenzó un listado? <a href="{{ route('reports.index') }}">Busque el registro y seleccione «Continuar agregando beneficiarios»</a>.</p>@endcan
            @endif
        </div>
    </section>

    <form enctype="multipart/form-data" class="report-form" id="report-form"
        data-beneficiary-url="{{ $continuing ? route('reports.continue-beneficiary', $report) : route('beneficiaries.store') }}" data-location-reverse-url="{{ route('locations.reverse') }}"
        data-continuing="{{ $continuing ? '1' : '0' }}"
        @if($editing) data-report-id="{{ $report->id }}" @endif
        @if($editingBeneficiary) data-beneficiary-update-url="{{ route('beneficiaries.update-attention', $editingBeneficiary) }}"
        @elseif($editing && !$continuing) data-report-update-url="{{ route('reports.update', $report) }}" @endif
        novalidate>
        @csrf
        @if($editingBeneficiary)<input type="hidden" name="source_report_id" value="{{ $report->id }}">@endif
        @if($continuing)
            <p class="alert alert-info" role="status">Este listado ya tiene {{ $initialBeneficiaries->count() }} beneficiario(s) guardado(s). Puede salir y retomarlo desde Registros. Solo se conservan las personas que haya guardado.</p>
            <fieldset class="report-continuation-context" disabled>
        @endif
        <section class="form-section">
            <div class="section-heading"><span>1</span>
                <div>
                    <h2>Actividad</h2>
                    <p>{{ $continuing ? 'Datos del listado original. Para una actividad diferente, inicie un nuevo registro.' : ($editingBeneficiary ? 'Proyecto, indicador, actividad y servicios de este beneficiario.' : ($editing ? 'Información compartida por todos los beneficiarios del grupo.' : 'Si cambia cualquiera de estos encabezados, el próximo beneficiario iniciará un nuevo registro.')) }}</p>
                </div>
            </div>
            <div class="form-grid ">
                <label>Proyecto *<select name="proyecto_id" id="proyecto_id" required>
                        <option value="" @selected(blank($selectedProjectId))>Seleccione un proyecto</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected((string) $selectedProjectId === (string) $project->id)>{{ $project->codigo }} — {{ $project->descripcion }}</option>
                        @endforeach
                    </select>
                </label><br>
                <label>Sector *<select name="sector_proyecto_id" id="sector_proyecto_id" required>
                        <option value="">Seleccione primero el proyecto</option>
                    </select>
                </label><br>
                <div class="indicator-select-field">
                    <div class="indicator-picker-heading">
                        <div><label for="indicator-card-search">Seleccione indicador *</label><small>Seleccione un indicador principal para ver sus asociados. Vuelva a pulsarlo para desmarcarlo.</small></div>
                        <span id="indicator-card-count" class="indicator-card-count">0 indicadores</span>
                    </div>
                    <select class="indicator-native-select" name="indicador_proyecto_id" id="indicador_proyecto_id" required aria-hidden="true" tabindex="-1">
                        <option value="">Seleccione primero el sector</option>
                    </select>
                    <div class="indicator-search-wrap"><i class="ri-search-line" aria-hidden="true"></i><input id="indicator-card-search" type="search" placeholder="Buscar por código o descripción" autocomplete="off"></div>
                    <div id="indicator-card-grid" class="indicator-card-grid" role="radiogroup" aria-label="Indicadores disponibles"></div>
                    <p id="indicator-card-empty" class="indicator-card-empty" hidden>No hay indicadores disponibles para este sector.</p>
                    <span id="selected-indicator-summary" class="selected-indicator-summary" hidden>
                        <strong id="selected-indicator-code" class="selected-indicator-code"></strong>
                        <span id="selected-indicator-description" class="selected-indicator-description"></span>
                    </span>
                </div><br>
                @unless($editing)
                <div id="associated-indicators-home" hidden>
                <section id="associated-indicators" class="indicator-association-branches" hidden aria-labelledby="associated-indicators-title">
                    <h3 id="associated-indicators-title">Indicadores asociados <small>(opcional)</small></h3>
                    <p class="muted">Seleccione uno o varios. Se guardará un registro independiente por cada indicador marcado, además del principal, con los mismos beneficiarios. Las actividades y servicios corresponden solo al principal.</p>
                    <div id="associated-indicator-options" class="indicator-group-items"></div>
                    <p id="associated-indicator-count" class="muted" role="status" aria-live="polite"></p>
                </section>
                </div>
                @endunless
                @if($editing && !empty($storedAssociatedIds))
                    <p class="muted span-two">Este registro tiene {{ count($storedAssociatedIds) }} indicador(es) asociado(s). Al agregar una persona también se guardará en esos registros. La edición o eliminación de personas ya guardadas afecta únicamente al registro que esté editando.</p>
                @endif
                <label id="report-activity-field" hidden>Actividad a reportar <small>(opcional)</small><select name="actividad_indicador_id" id="actividad_indicador_id">
                        <option value="">Seleccione primero el indicador</option>
                    </select>
                </label>
                <label class="span-two" id="report-services-field" hidden>Servicios <small>(opcional)</small>
                    <select name="servicio_actividad_ids[]" id="servicio_actividad_ids" multiple></select>
                    <small class="muted">Puede seleccionar uno o varios servicios.</small>
                </label>
                <label class="span-two">Detalles adicionales de la actividad
                    <textarea name="activity_details" rows="4" maxlength="300"
                        placeholder="Cantidades entregadas, temas de capacitación, logros o detalles relevantes.">{{ $contextOld('activity_details', $editing ? $report->activity_details : null) }}</textarea>
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
                        value="{{ $registrant['reporter_first_name'] }}" readonly required></label>
                <label>Apellido<input type="text" name="reporter_last_name"
                        value="{{ $registrant['reporter_last_name'] }}" readonly></label>
                <label>Correo electrónico *<input type="email" name="reporter_email"
                        value="{{ $registrant['reporter_email'] }}" readonly required></label>
            </div>
            <br>
            <div class="form-grid three-cols">
                <label>Período<input type="text" id="report-period" value="{{ \App\Support\ReportPeriod::label($reportingPeriod) }}" readonly aria-readonly="true"></label>
                @if(!$editing)<input type="hidden" name="period_snapshot" value="{{ $reportingPeriod }}">@endif
                <label>Fecha de atencion *<input type="date" name="report_date"
                        value="{{ $contextOld('report_date', $editing ? $report->report_date->format('Y-m-d') : today()->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}"
                        required></label>

                <label>Organización implementadora *
                    <select name="organization" id="organization" required>
                        <option value="">Seleccione una organización</option>
                        @foreach ($organizations as $organization)
                            <option value="{{ $organization }}" @selected($contextOld('organization', $editing ? $report->organization : 'ASONACOP') === $organization)>{{ $organization }}</option>
                        @endforeach
                    </select>
                </label>
                <label id="other-organization-field" hidden>Especifique otra organización<input type="text"
                        name="other_organization" value="{{ $contextOld('other_organization', $editing ? $report->other_organization : null) }}"></label>
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
                        @checked($contextOld('is_community_location', $communityLocation))>
                    No es un centro o espacio de atención formal o provisional
                </label>
                <div id="formal-place-fields">
                <label>Nombre específico del lugar *
                    <select name="place_name" id="place_name" required>
                        <option value="">Seleccione el nombre del lugar</option>
                        @if($editing)
                            <option value="{{ $report->place_name }}" data-original-location="1"
                                data-state-id="{{ $report->state_id }}" data-state-name="{{ $report->state?->name }}"
                                data-municipality-id="{{ $report->municipality_id }}" data-municipality-name="{{ $report->municipality?->name }}"
                                data-parish-id="{{ $report->parish_id }}" data-parish-name="{{ $report->parish?->name }}"
                                data-installation-type="{{ $report->installation_type }}"
                                data-latitude="{{ $report->latitude }}" data-longitude="{{ $report->longitude }}"
                                data-altitude="{{ $report->altitude }}" data-gps-accuracy="{{ $report->gps_accuracy }}"
                                @selected($contextOld('place_name', $report->place_name) === $report->place_name)>{{ $report->place_name }} (ubicación guardada)</option>
                        @endif
                        @foreach ($placeNames as $placeName)
                            <option value="{{ $placeName->name }}" data-place-id="{{ $placeName->id }}" data-state-id="{{ $placeName->state_id }}"
                                data-state-name="{{ $placeName->state?->name }}"
                                data-municipality-id="{{ $placeName->municipality_id }}"
                                data-municipality-name="{{ $placeName->municipality?->name }}"
                                data-parish-id="{{ $placeName->parish_id }}"
                                data-parish-name="{{ $placeName->parish?->name }}"
                                data-installation-type="{{ $placeName->installation_type }}"
                                data-latitude="{{ $placeName->latitude }}"
                                data-longitude="{{ $placeName->longitude }}"
                                data-altitude="{{ $placeName->altitude }}"
                                data-gps-accuracy="{{ $placeName->gps_accuracy }}" @selected($contextOld('place_name', $editing ? $report->place_name : null) === $placeName->name)>
                                {{ $placeName->name }}</option>
                        @endforeach
                    </select>
                    <small id="project-place-hint">Seleccione un proyecto para ver sus lugares asignados.</small>
                    @can('manejar lugares')
                        <small>¿No aparece? <a href="{{ route('place-names.index') }}" target="_blank" rel="noopener">Crear o
                                administrar nombres de lugares</a>.</small>
                    @endcan
                    <small id="place-location-summary" class="place-location-summary" hidden></small>
                </label>
                </div>
                <div class="form-grid three-cols community-location-fields" id="community-location-fields" hidden>
                    <label>Estado *
                        <select id="community_state_id">
                            <option value="">Seleccione el estado</option>
                            @foreach($states as $stateOption)
                                <option value="{{ $stateOption->id }}" @selected($contextOld('state_id', $editing ? $report->state_id : null) == $stateOption->id)>{{ $stateOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Municipio *
                        <select id="community_municipality_id">
                            <option value="">Seleccione el municipio</option>
                            @foreach($communityMunicipalities as $municipalityOption)
                                <option value="{{ $municipalityOption->id }}" @selected($contextOld('municipality_id', $editing ? $report->municipality_id : null) == $municipalityOption->id)>{{ $municipalityOption->name }}</option>
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
                                    @selected($contextOld('parish_id', $editing ? $report->parish_id : null) == $parishOption->id)>{{ $parishOption->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="community-location-summary span-two" id="community-location-summary" hidden>
                        <strong id="community-generated-name" class="span-two"></strong>
                        <label>Latitud específica *
                            <input type="number" id="community-latitude" step="any" min="-90" max="90"
                                value="{{ $contextOld('latitude', $editing ? $report->latitude : null) }}">
                        </label>
                        <label>Longitud específica *
                            <input type="number" id="community-longitude" step="any" min="-180" max="180"
                                value="{{ $contextOld('longitude', $editing ? $report->longitude : null) }}">
                        </label>
                        <small class="span-two muted">Puede reemplazar las coordenadas sugeridas por unas más precisas.</small>
                    </div>
                </div>
            </div>
            <input type="hidden" name="state_id" id="state_id" value="{{ $contextOld('state_id', $editing ? $report->state_id : null) }}">
            <input type="hidden" name="municipality_id" id="municipality_id" value="{{ $contextOld('municipality_id', $editing ? $report->municipality_id : null) }}">
            <input type="hidden" name="parish_id" id="parish_id" value="{{ $contextOld('parish_id', $editing ? $report->parish_id : null) }}">
            <input type="hidden" name="installation_type" id="installation_type" value="{{ $contextOld('installation_type', $editing ? $report->installation_type : null) }}">
            <input type="hidden" name="latitude" id="latitude" value="{{ $contextOld('latitude', $editing ? $report->latitude : null) }}">
            <input type="hidden" name="longitude" id="longitude" value="{{ $contextOld('longitude', $editing ? $report->longitude : null) }}">
            <input type="hidden" name="altitude" id="altitude" value="{{ $contextOld('altitude', $editing ? $report->altitude : null) }}">
            <input type="hidden" name="gps_accuracy" id="gps_accuracy" value="{{ $contextOld('gps_accuracy', $editing ? $report->gps_accuracy : null) }}">
        </section>

        @if($continuing)</fieldset>@endif
        <section class="form-section" @if($editing && !$editingBeneficiary && !$continuing) hidden @endif>
            <div class="section-heading"><span>4</span>
                <div>
                    <h2>Beneficiarios</h2>
                    <p>{{ $editingBeneficiary ? 'Edite los datos de esta persona y guarde todos sus cambios.' : 'Complete una persona y guárdela. Los campos de esta sección se limpiarán, pero los encabezados permanecerán.' }}</p>
                </div>
            </div>
            <fieldset class="beneficiary-entry" @if($editing && !$editingBeneficiary && !$continuing) disabled @endif>
                <legend id="beneficiary-entry-title">Registrar beneficiario</legend>
                <div class="beneficiary-consent-card">
                    <div class="form-check form-check-primary">
                        <input class="form-check-input" type="checkbox" id="beneficiary_has_informed_consent" checked required>
                        <label class="form-check-label" for="beneficiary_has_informed_consent">
                            Cuenta con el consentimiento informado del beneficiario?
                        </label>
                    </div>
                    <p>Marque esta casilla cuando el beneficiario haya otorgado su consentimiento informado.</p>
                </div>
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
                            placeholder="Ejemplo: Cédula V18456782, nombre María González, edad 34 años, mujer, teléfono 0414 123 4567, sin discapacidad, no indígena, lactante."></textarea>
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
                <p class="beneficiary-voice-example"><strong>Ejemplo:</strong> Cédula V18456782, nombre María González, edad 34 años, mujer, teléfono 0414 123 4567, sin discapacidad, no indígena, lactante.</p>
                <p class="beneficiary-voice-example"><strong>Menor de edad:</strong> Sin cédula, nombre Ana Pérez, edad 12 años, mujer, cédula del cuidador V12345678, nombre del cuidador Carmen Pérez, parentesco Madre.</p>
                <p class="beneficiary-voice-example"><strong>Comando:</strong> diga “guardar beneficiario” para registrar la persona por voz.</p>
                <div class="form-grid beneficiary-form-grid">
                    <label>Cédula<input type="text" id="beneficiary_national_id" maxlength="30"
                            inputmode="text" data-beneficiary-lookup="{{ route('beneficiaries.lookup') }}"
                            data-lookup-enabled="{{ (!$editing || $continuing) && auth()->user()->can('registrar actividad') && auth()->user()->can('ver detalle de registros') ? '1' : '0' }}"
                            aria-describedby="beneficiary-lookup-status" autocomplete="off">
                        <small id="beneficiary-lookup-status" role="status" aria-live="polite">Al ingresar la cédula se consultan coincidencias para completar los datos. Revise la información antes de guardar.</small>
                    </label>
                    <label>Nombre y apellido *<input type="text" id="beneficiary_full_name"
                            required maxlength="150" autocomplete="name" style="text-transform: uppercase"></label>
                    <label>Edad * <small id="beneficiary-age-range" class="beneficiary-age-range" hidden></small><input type="number" id="beneficiary_age" min="0" max="120"
                            inputmode="numeric"></label>
                    <label>Sexo *<select id="beneficiary_sex">
                            <option value="">Seleccione</option>
                            @foreach ($beneficiaryOptions['sexes'] as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                    </label>
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
                    <label>Nacionalidad<select id="beneficiary_nationality">
                            <option value="">Sin especificar</option>
                            @foreach ($nationalityOptions as $option)
                                <option value="{{ $option }}" data-nationality-legacy="{{ in_array($option, $beneficiaryOptions['nationalities'], true) ? '0' : '1' }}" @disabled(!in_array($option, $beneficiaryOptions['nationalities'], true)) @selected($option === old('beneficiary.nationality', \App\Models\Beneficiary::DEFAULT_NATIONALITY))>{{ $option }}</option>
                            @endforeach
                        </select></label>
                </div>
                <fieldset id="beneficiary-caregiver-fields" class="beneficiary-caregiver-fields" hidden>
                    <legend>Datos del cuidador</legend>
                    <p class="muted">Datos opcionales para beneficiarios menores de 18 años.</p>
                    <div class="form-grid beneficiary-form-grid">
                        <label>Cédula del cuidador (opcional)<input type="text" id="beneficiary_caregiver_national_id" maxlength="30" disabled></label>
                        <label>Nombre del cuidador (opcional)<input type="text" id="beneficiary_caregiver_full_name" maxlength="150" style="text-transform:uppercase" disabled></label>
                        <label>Parentesco del cuidador (opcional)<select id="beneficiary_caregiver_relationship" disabled>
                            <option value="">Seleccione el parentesco</option>
                            @foreach($caregiverRelationshipOptions as $option)
                                <option value="{{ $option }}" data-caregiver-relationship-legacy="{{ in_array($option, $beneficiaryOptions['caregiver_relationships'], true) ? '0' : '1' }}" @disabled(!in_array($option, $beneficiaryOptions['caregiver_relationships'], true)) @selected($option === old('beneficiary.caregiver_relationship'))>{{ $option }}</option>
                            @endforeach
                        </select></label>
                    </div>
                </fieldset>
                <div class="beneficiary-recurrence-field"><label>Recurrente *<select id="beneficiary_is_recurrent">
                            <option value="0" selected>No</option>
                            <option value="1">Sí</option>
                        </select></label>
                    <p class="muted">Se calcula automáticamente según los registros anteriores del beneficiario en el mismo proyecto.</p>
                </div>
                <div class="beneficiary-entry-actions">
                    <button
                        class="button button-secondary" type="button" id="save-beneficiary">Guardar
                        beneficiario</button><button class="button button-ghost" type="button"
                        id="cancel-beneficiary-edit" hidden>Cancelar edición</button>
                </div>
            </fieldset>

            <div class="beneficiary-list-card" @if($editing && !$continuing) hidden @endif>
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
                                <th>Consentimiento</th>
                                <th>Edad</th>
                                <th>Sexo</th>
                                <th>Nacionalidad</th>
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

        <p id="beneficiary-entry-error" class="field-error" role="alert" hidden></p>
        <p id="beneficiary-entry-success" class="field-success" role="status" hidden></p>
        <div class="form-actions"><a class="button button-ghost" href="{{ $editing ? route('reports.show', $report) : route('dashboard') }}">Cancelar</a>
            @if($editing && !$continuing)<button class="button button-primary" type="button" id="save-report-changes">{{ $editingBeneficiary ? 'Guardar cambios del beneficiario' : 'Guardar cambios de todo el grupo' }}</button>@endif
            @can('ver detalle de registros')<a class="button button-secondary" id="current-report-link" href="{{ $editing ? route('reports.show', $report) : '#' }}" @if(!$editing) hidden @endif>Ver registro guardado</a>@endcan
        </div>
    </form>

@endsection

@push('scripts')
    <script src="{{ asset('js/beneficiary-recurrence.js') }}?v={{ filemtime(public_path('js/beneficiary-recurrence.js')) }}"></script>
    <script src="{{ asset('js/beneficiary-voice.js') }}?v={{ filemtime(public_path('js/beneficiary-voice.js')) }}"></script>
    <script src="{{ asset('js/associated-indicators.js') }}?v={{ filemtime(public_path('js/associated-indicators.js')) }}"></script>
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
            projectSector = select('sector_proyecto_id'),
            activity = select('indicador_proyecto_id'),
            indicatorCardGrid = select('indicator-card-grid'),
            indicatorCardSearch = select('indicator-card-search'),
            indicatorCardCount = select('indicator-card-count'),
            indicatorCardEmpty = select('indicator-card-empty'),
            indicatorSummary = select('selected-indicator-summary'),
            indicatorCode = select('selected-indicator-code'),
            indicatorDescription = select('selected-indicator-description'),
            beneficiaryAgeRange = select('beneficiary-age-range'),
            reportedActivity = select('actividad_indicador_id'),
            reportedActivityField = select('report-activity-field'),
            services = select('servicio_actividad_ids'),
            servicesField = select('report-services-field');
        const projectIndicators = @json($projectIndicatorOptions);
        const projectLocations = @json($projectLocationOptions);
        const initialProjectSector = @json($contextOld('sector_proyecto_id', $editing ? $report->indicadorProyecto?->sector_proyecto_id : null));
        const initialIndicator = @json($contextOld('indicador_proyecto_id', $editing ? $report->indicador_proyecto_id : null));
        const initialActivity = @json($contextOld('actividad_indicador_id', $editing ? $report->actividad_indicador_id : null));
        const initialServices = @json($contextOld('servicio_actividad_ids', $editing ? $report->serviciosActividad->pluck('id')->all() : []));
        const projectSectors = () => {
            const unique = new Map();
            (projectIndicators[project.value] || []).forEach(item => {
                if (item.sectorProjectId && !unique.has(String(item.sectorProjectId))) {
                    unique.set(String(item.sectorProjectId), {
                        id: item.sectorProjectId,
                        title: [item.sectorCode, item.sectorTitle].filter(Boolean).join(' — '),
                    });
                }
            });
            return [...unique.values()];
        };
        const selectedSectorIndicators = () => window.primaryIndicatorOptions(
            projectIndicators[project.value] || [], projectSector.value
        );
        const selectedIndicator = () => selectedSectorIndicators().find(item => String(item.id) === String(activity.value));
        const associatedPicker = window.createAssociatedIndicatorPicker(select('associated-indicators'));
        const storedAssociatedIds = @json($storedAssociatedIds ?? []);
        let associatedPrincipal = null;
        const syncAssociatedIndicators = (selected = null) => {
            const selectedIds = selected ?? (associatedPrincipal === activity.value ? associatedPicker.selectedIds() : []);
            associatedPrincipal = activity.value;
            const ids = (selectedIndicator()?.associatedIds || []).map(String);
            associatedPicker.render((projectIndicators[project.value] || []).filter(item => ids.includes(String(item.id))), selectedIds);
        };
        const selectedIndicatorAgeRange = () => {
            const indicator = selectedIndicator();
            if (!indicator || indicator.unit !== 'Personas') return null;
            return {from: Number(indicator.ageFrom), to: Number(indicator.ageTo)};
        };
        const syncBeneficiaryAgeRange = () => {
            const range = selectedIndicatorAgeRange();
            const ageInput = select('beneficiary_age');
            ageInput.min = range ? String(range.from) : '0';
            ageInput.max = range ? String(range.to) : '120';
            beneficiaryAgeRange.textContent = range ? `Rango permitido: ${range.from} a ${range.to} años` : '';
            beneficiaryAgeRange.hidden = !range;
        };
        const syncIndicatorDescription = () => {
            const indicator = selectedIndicator();
            indicatorCode.textContent = indicator?.code || '';
            indicatorDescription.textContent = indicator?.title || '';
            indicatorSummary.hidden = !indicator;
        };
        const selectedProjectActivity = () => (selectedIndicator()?.activities || []).find(item => String(item.id) === String(reportedActivity.value));
        const syncServices = (selected = []) => {
            const items = selectedProjectActivity()?.services || [];
            const selectedIds = selected.map(String);
            services.innerHTML = items.map(item => `<option value="${item.id}" ${selectedIds.includes(String(item.id)) ? 'selected' : ''}>${item.title}</option>`).join('');
            servicesField.hidden = items.length === 0;
            services.required = false;
            if (window.jQuery && jQuery.fn.select2) jQuery(services).trigger('change.select2');
        };
        const syncIndicatorActivities = (selected = '', selectedServices = []) => {
            syncIndicatorDescription();
            syncBeneficiaryAgeRange();
            const activities = selectedIndicator()?.activities || [];
            reportedActivityField.hidden = activities.length === 0;
            setOptions(reportedActivity, activities, activity.value ? 'Seleccione una actividad' : 'Seleccione primero el indicador', selected);
            if (activities.length === 0) reportedActivity.value = '';
            syncServices(selectedServices);
        };
        const coordinationClass = value => {
            const normalized = String(value || '').toLocaleUpperCase('es-VE');
            if (normalized === 'VBG') return 'is-vbg';
            if (normalized === 'NNA/VBG') return 'is-mixed';
            return 'is-nna';
        };
        const renderIndicatorCards = () => {
            const query = indicatorCardSearch.value.trim().toLocaleLowerCase('es-VE');
            const indicators = selectedSectorIndicators().filter(item =>
                String(item.id) === String(activity.value) || !query || `${item.code} ${item.shortName || ''} ${item.title} ${item.coordination} ${item.groupName || ''}`.toLocaleLowerCase('es-VE').includes(query)
            );
            // Move the existing controls before rebuilding cards: retain selections and form ownership.
            associatedPicker.park();
            indicatorCardGrid.replaceChildren();
            const groupedIndicators = new Map();
            indicators.forEach(item => {
                const key = item.groupId ? `group-${item.groupId}` : 'ungrouped';
                if (!groupedIndicators.has(key)) {
                    groupedIndicators.set(key, {
                        name: item.groupName || 'Sin grupo',
                        description: item.groupDescription || (item.groupId ? '' : 'Indicadores pendientes de clasificación.'),
                        order: item.groupId ? Number(item.groupOrder ?? 0) : Number.MAX_SAFE_INTEGER,
                        items: [],
                    });
                }
                groupedIndicators.get(key).items.push(item);
            });
            [...groupedIndicators.values()]
                .sort((left, right) => left.order - right.order || left.name.localeCompare(right.name, 'es'))
                .forEach(group => {
                    group.items.sort((left, right) => String(left.code).localeCompare(String(right.code), 'es', {
                        numeric: true,
                        sensitivity: 'base',
                    }));
                    const panel = document.createElement('section');
                    panel.className = 'indicator-group-panel';

                    const heading = document.createElement('header');
                    heading.className = 'indicator-group-header';
                    const title = document.createElement('strong');
                    title.textContent = `${group.order === Number.MAX_SAFE_INTEGER ? '' : `${group.order}. `}${group.name}`;
                    const count = document.createElement('span');
                    count.className = 'indicator-group-total';
                    count.textContent = String(group.items.length);
                    heading.append(title, count);
                    panel.append(heading);

                    if (group.description) {
                        const groupDescription = document.createElement('p');
                        groupDescription.className = 'indicator-group-description';
                        groupDescription.textContent = group.description;
                        panel.append(groupDescription);
                    }

                    const items = document.createElement('div');
                    items.className = 'indicator-group-items';
                    group.items.forEach(item => {
                        const card = document.createElement('button');
                        const isSelected = String(item.id) === String(activity.value);
                        card.type = 'button';
                        card.className = `indicator-card ${isSelected ? 'is-selected' : ''}`;
                        card.setAttribute('role', 'radio');
                        card.setAttribute('aria-checked', isSelected ? 'true' : 'false');

                const top = document.createElement('span');
                top.className = 'indicator-card-top';
                const badge = document.createElement('span');
                badge.className = `indicator-coordination ${coordinationClass(item.coordination)}`;
                badge.textContent = item.coordination || 'General';
                const code = document.createElement('strong');
                code.textContent = item.code;
                const check = document.createElement('i');
                check.className = 'ri-checkbox-circle-fill indicator-card-check';
                check.setAttribute('aria-hidden', 'true');
                top.append(badge, code, check);

                const description = document.createElement('span');
                description.className = 'indicator-card-description';
                description.textContent = item.shortName || item.title;
                const meta = document.createElement('span');
                meta.className = 'indicator-card-meta';
                meta.textContent = `${item.unit || 'Sin unidad'} · Edad: ${item.ageFrom ?? 0} a ${item.ageTo ?? 120} años`;
                card.append(top, description, meta);
                card.addEventListener('click', () => {
                    window.togglePrimaryIndicator(activity, item.id);
                });
                        if (isSelected && item.associatedIds?.length && !@json($editing)) {
                            const branch = document.createElement('div');
                            branch.className = 'indicator-branch';
                            card.classList.add('indicator-branch-root');
                            card.setAttribute('aria-controls', 'associated-indicators');
                            card.setAttribute('aria-expanded', 'true');
                            branch.append(card);
                            associatedPicker.mount(branch);
                            items.append(branch);
                        } else {
                            items.append(card);
                        }
                    });
                    panel.append(items);
                    indicatorCardGrid.append(panel);
                });
            const total = selectedSectorIndicators().length;
            indicatorCardCount.textContent = `${total} ${total === 1 ? 'indicador' : 'indicadores'}`;
            indicatorCardEmpty.textContent = projectSector.value
                ? 'No hay indicadores que coincidan con la búsqueda.'
                : 'Seleccione un sector para ver sus indicadores.';
            indicatorCardEmpty.hidden = indicators.length !== 0;
        };
        const syncProjectIndicators = (selected = '', selectedActivity = '', selectedServices = []) => {
            setOptions(activity, selectedSectorIndicators(), projectSector.value ? 'Seleccione un indicador' : 'Seleccione primero el sector', selected);
            indicatorCardSearch.value = '';
            renderIndicatorCards();
            syncIndicatorActivities(selectedActivity, selectedServices);
            syncAssociatedIndicators();
        };
        const syncProjectSectors = (selected = '', selectedIndicator = '', selectedActivity = '', selectedServices = []) => {
            setOptions(projectSector, projectSectors(), project.value ? 'Seleccione un sector' : 'Seleccione primero el proyecto', selected);
            syncProjectIndicators(selectedIndicator, selectedActivity, selectedServices);
        };
        project.addEventListener('change', () => syncProjectSectors());
        projectSector.addEventListener('change', () => syncProjectIndicators());
        indicatorCardSearch.addEventListener('input', renderIndicatorCards);
        reportedActivity.addEventListener('change', () => syncServices());
        if (window.jQuery && jQuery.fn.select2) {
            jQuery(services).select2({width: '100%', placeholder: 'Seleccione uno o varios servicios'});
        }
        activity.addEventListener('change', () => {
            syncIndicatorActivities();
            renderIndicatorCards();
            syncAssociatedIndicators();
        });
        syncProjectSectors(initialProjectSector, initialIndicator, initialActivity, initialServices);
        syncAssociatedIndicators(@json(old('associated_indicator_ids', [])));
        const placeName = select('place_name'),
            installationType = select('installation_type'),
            placeLocationSummary = select('place-location-summary');
        const allPlaceOptions = [...placeName.options].map(option => option.cloneNode(true));
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
        const initialCommunityCoordinates = {
            latitude: @json($contextOld('latitude', $editing ? $report->latitude : null)),
            longitude: @json($contextOld('longitude', $editing ? $report->longitude : null)),
        };
        const allCommunityStateOptions = [...communityState.options].map(option => option.cloneNode(true));
        const locationBelongsToProject = (stateId, municipalityId = null) => {
            const location = projectLocations[project.value];
            if (!location || !location.states.map(String).includes(String(stateId))) return false;
            if (municipalityId === null) return true;
            return location.allStateMunicipalities
                || location.municipalities.map(String).includes(String(municipalityId));
        };
        const syncAvailableLocations = (preserveSelection = false) => {
            const selectedPlace = preserveSelection ? placeName.value : '';
            const assignedPlaces = new Set((projectLocations[project.value]?.places || []).map(String));
            const retainOriginal = project.value === @json($editing ? (string) $report->proyecto_id : '') && @json($editing);
            const originalName = @json($editing ? $report->place_name : null);
            placeName.replaceChildren();
            allPlaceOptions.forEach(option => {
                const available = option.dataset.originalLocation
                    ? retainOriginal
                    : assignedPlaces.has(String(option.dataset.placeId))
                        && locationBelongsToProject(option.dataset.stateId, option.dataset.municipalityId)
                        && !(retainOriginal && option.value === originalName);
                if (!option.value || available) {
                    const copy = option.cloneNode(true);
                    copy.selected = copy.value === selectedPlace;
                    placeName.append(copy);
                }
            });
            select('project-place-hint').textContent = !project.value
                ? 'Seleccione un proyecto para ver sus lugares asignados.'
                : retainOriginal
                    ? 'Se conserva la ubicación guardada del registro. Los demás lugares corresponden al proyecto y a su usuario.'
                    : placeName.options.length > 1
                    ? 'Solo se muestran los lugares asignados al proyecto y disponibles para su usuario.'
                    : 'Este proyecto no tiene lugares del catálogo disponibles para su usuario. Solicite al administrador que los configure.';

            const selectedState = preserveSelection ? communityState.value : '';
            communityState.replaceChildren();
            allCommunityStateOptions.forEach(option => {
                if (!option.value || locationBelongsToProject(option.value)) {
                    const copy = option.cloneNode(true);
                    copy.selected = copy.value === selectedState;
                    communityState.append(copy);
                }
            });

            if (!placeName.value) clearCommunityLocation();
            else syncPlaceLocation();
            if (!communityState.value) {
                setCommunityOptions(communityMunicipality, [], 'Seleccione el municipio');
                setCommunityOptions(communityParish, [], 'Seleccione la parroquia');
            }
        };
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
            communityLatitude.value = '';
            communityLongitude.value = '';
        };
        const syncCommunityLocation = (coordinates = null) => {
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
            communityLatitude.value = coordinates?.latitude || option.dataset.latitude || '';
            communityLongitude.value = coordinates?.longitude || option.dataset.longitude || '';
            select('latitude').value = communityLatitude.value;
            select('longitude').value = communityLongitude.value;
            communityLocationSummary.hidden = false;
            communityGeneratedName.textContent = `Nombre del lugar: ${generatedName}`;
        };
        const syncCommunityMode = (coordinates = null) => {
            const enabled = communityLocationToggle.checked;
            formalPlaceFields.hidden = enabled;
            communityLocationFields.hidden = !enabled;
            placeName.required = !enabled;
            [communityState, communityMunicipality, communityParish].forEach(element => element.required = enabled);
            [communityLatitude, communityLongitude].forEach(element => element.required = enabled);
            if (enabled) syncCommunityLocation(coordinates);
            else {
                const selectedPlace = placeName.value;
                clearCommunityLocation();
                placeName.value = selectedPlace;
                syncPlaceLocation();
            }
        };
        communityState.addEventListener('change', async () => {
            clearCommunityLocation();
            setCommunityOptions(communityMunicipality, [], 'Cargando municipios');
            setCommunityOptions(communityParish, [], 'Seleccione la parroquia');
            if (communityState.value) {
                const response = await fetch(`/ubicaciones/estados/${communityState.value}/municipios`, {headers: {'Accept': 'application/json'}});
                const municipalities = await response.json();
                setCommunityOptions(communityMunicipality, municipalities.filter(item => locationBelongsToProject(communityState.value, item.id)), 'Seleccione el municipio');
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
        communityLatitude.addEventListener('input', () => select('latitude').value = communityLatitude.value);
        communityLongitude.addEventListener('input', () => select('longitude').value = communityLongitude.value);
        communityLocationToggle.addEventListener('change', syncCommunityMode);
        project.addEventListener('change', () => syncAvailableLocations(false));
        syncAvailableLocations(true);
        syncCommunityMode(initialCommunityCoordinates);
        const validateCoordinates = async () => true;

        const beneficiaryFields = ['has_informed_consent', 'full_name', 'age', 'sex', 'national_id', 'nationality', 'phone', 'disability', 'ethnicity',
            'pregnant_lactating', 'is_recurrent', 'caregiver_national_id', 'caregiver_full_name', 'caregiver_relationship'
        ];
        const beneficiaryInputs = Object.fromEntries(beneficiaryFields.map(field => [field, select(
            `beneficiary_${field}`)]));
        const syncCaregiverFields = () => {
            const age = beneficiaryInputs.age.value.trim();
            const minor = age !== '' && Number.isInteger(Number(age)) && Number(age) >= 0 && Number(age) < 18;
            const adult = age !== '' && Number.isInteger(Number(age)) && Number(age) >= 18;
            select('beneficiary-caregiver-fields').hidden = !minor;
            ['caregiver_national_id', 'caregiver_full_name', 'caregiver_relationship'].forEach(field => {
                const input = beneficiaryInputs[field];
                input.required = false;
                input.disabled = !minor;
                if (adult) input.value = '';
            });
        };
        const syncNationalityOptions = storedNationality => {
            Array.from(beneficiaryInputs.nationality.options).forEach(option => {
                if (option.dataset.nationalityLegacy === '1') option.disabled = option.value !== storedNationality;
            });
        };
        const syncCaregiverRelationshipOptions = storedRelationship => {
            Array.from(beneficiaryInputs.caregiver_relationship.options).forEach(option => {
                if (option.dataset.caregiverRelationshipLegacy === '1') option.disabled = option.value !== storedRelationship;
            });
        };
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
            'proyecto_id', 'indicador_proyecto_id', 'actividad_indicador_id', 'servicio_actividad_ids[]', 'associated_indicator_ids[]'
        ];
        let beneficiaries = @json($initialBeneficiaries),
            activeReportId = form.dataset.reportId || null,
            activeHeaderSignature = null,
            beneficiaryEditId = null,
            isSaving = false;
        let currentSummary = {
            total: {{ $editing ? $initialSummary['total'] : 0 }},
            people_with_disabilities: {{ $editing ? $initialSummary['people_with_disabilities'] : 0 }},
            indigenous_people: {{ $editing ? $initialSummary['indigenous_people'] : 0 }},
            pregnant_or_lactating_women: {{ $editing ? $initialSummary['pregnant_or_lactating_women'] : 0 }}
        };
        const inputValue = field => field === 'has_informed_consent'
            ? (beneficiaryInputs[field].checked ? '1' : '0')
            : beneficiaryInputs[field].value.trim();
        const beneficiaryRecord = () => Object.fromEntries(beneficiaryFields.map(field => [field, inputValue(field)]));
        const headerSignature = () => JSON.stringify(Object.fromEntries(headerFields.map(field => {
            if (field === 'associated_indicator_ids[]') return [field, associatedPicker.selectedIds()];
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
        let voiceRecognition = null, voiceSessionActive = false, voiceAcceptResults = false,
            voiceSessionBase = '', voiceGeneration = 0;
        const choices = field => Array.from(beneficiaryInputs[field].options)
            .filter(option => !option.disabled).map(option => option.value);
        const voiceCoordinator = window.BeneficiaryVoice.createCoordinator({
            inputs: beneficiaryInputs,
            options: {nationalities: choices('nationality'), disabilities: choices('disability'),
                ethnicities: choices('ethnicity'), caregiver_relationships: choices('caregiver_relationship')},
            lookup: id => form.beneficiaryLookup?.forVoice(id) || {status: 'disabled', fields: []},
            sync: () => {syncPregnantLactatingField(); syncCaregiverFields(); void checkPossibleRecurrence();},
            status: message => {voiceStatus.textContent = message;},
            pending: value => {form.dataset.beneficiaryVoicePending = value ? '1' : '0';},
        });
        const markVoiceField = (field, recognized) => {
            beneficiaryInputs[field].classList.toggle('voice-recognized', recognized);
            beneficiaryInputs[field].classList.toggle('voice-review', !beneficiaryInputs[field].value.trim() && ['full_name', 'age', 'sex'].includes(field));
        };
        const applyVoiceTranscript = async (text = voiceText.value) => {
            if (!text.trim()) {
                voiceStatus.textContent = 'Dicte o escriba primero los datos del beneficiario.';
                voiceText.focus();
                return {canSave: false};
            }
            const result = await voiceCoordinator.apply(text);
            if (result.cancelled) return result;
            const recognized = new Set(result.recognized || []);
            beneficiaryFields.forEach(field => markVoiceField(field, recognized.has(field)));
            const missing = ['full_name', 'age', 'sex'].find(field => !beneficiaryInputs[field].value.trim());
            if (missing) beneficiaryInputs[missing].focus();
            return result;
        };
        const resetVoiceButton = () => {
            voiceToggle.classList.remove('is-listening');
            voiceToggle.setAttribute('aria-pressed', 'false');
            voiceToggle.textContent = SpeechRecognition ? 'Dictar beneficiario' : 'Escribir datos rápidos';
        };
        const cancelVoice = () => {
            voiceGeneration++;
            voiceAcceptResults = false;
            voiceSessionActive = false;
            voiceCoordinator.cancel();
            form.beneficiaryLookup?.cancel();
            voiceRecognition?.abort();
            resetVoiceButton();
        };
        form.addEventListener('beneficiary-entry-reset', cancelVoice);
        window.addEventListener('pagehide', cancelVoice);
        document.addEventListener('visibilitychange', () => {if (document.hidden) cancelVoice();});
        if (SpeechRecognition) {
            voiceRecognition = new SpeechRecognition();
            voiceRecognition.lang = 'es-VE';
            voiceRecognition.interimResults = true;
            voiceRecognition.continuous = true;
            voiceRecognition.onstart = () => {
                voicePanel.hidden = false;
                voiceToggle.classList.add('is-listening');
                voiceToggle.setAttribute('aria-pressed', 'true');
                voiceToggle.textContent = 'Detener dictado';
                voiceStatus.textContent = 'Escuchando… comience por la cédula o diga “sin cédula”.';
            };
            voiceRecognition.onresult = async event => {
                if (!voiceAcceptResults) return;
                const generation = voiceGeneration;
                const results = Array.from(event.results);
                voiceText.value = [voiceSessionBase, ...results.map(result => result[0].transcript)].filter(Boolean).join(', ');
                const latestResult = results.at(-1);
                // Preview interim speech without querying an unfinished ID.
                if (!latestResult?.isFinal) return;
                const confirmed = [voiceSessionBase, ...results.filter(result => result.isFinal).map(result => result[0].transcript)].filter(Boolean).join(', ');
                const saveRequested = window.BeneficiaryVoice.parse(latestResult[0].transcript).saveRequested;
                if (saveRequested) {
                    voiceAcceptResults = false;
                    voiceSessionActive = false;
                    voiceRecognition.stop();
                }
                const result = await applyVoiceTranscript(confirmed);
                if (generation !== voiceGeneration || result.cancelled) return;
                if (saveRequested && result.canSave) {
                    voiceStatus.textContent = 'Comando reconocido. Comprobando los datos antes de guardar…';
                    await saveBeneficiary();
                } else if (saveRequested) voiceStatus.textContent += ' Revise los datos y guarde manualmente.';
            };
            voiceRecognition.onerror = event => {
                const errors = {'not-allowed':'No se concedió permiso para usar el micrófono.',
                    'no-speech':'No se detectó voz. Inténtelo nuevamente.',
                    'audio-capture':'No se encontró un micrófono disponible.'};
                if (event.error !== 'aborted') voiceStatus.textContent = errors[event.error] || 'No fue posible reconocer la voz. También puede escribir la frase.';
                voiceSessionActive = false;
                voiceAcceptResults = false;
                resetVoiceButton();
            };
            voiceRecognition.onend = () => {
                voiceSessionActive = false;
                resetVoiceButton();
            };
        } else select('beneficiary-voice-unavailable').hidden = false;
        voiceToggle.addEventListener('click', () => {
            voicePanel.hidden = false;
            if (!voiceRecognition) return voiceText.focus();
            if (voiceSessionActive) {
                voiceSessionActive = false;
                voiceRecognition.stop();
            } else {
                voiceSessionBase = voiceText.value.trim();
                voiceGeneration++;
                voiceCoordinator.cancel();
                voiceSessionActive = true;
                voiceAcceptResults = true;
                try {voiceRecognition.start();}
                catch (_) {
                    voiceSessionActive = false;
                    voiceAcceptResults = false;
                    resetVoiceButton();
                    voiceStatus.textContent = 'Espere a que termine el dictado anterior e intente nuevamente.';
                }
            }
        });
        select('beneficiary-voice-apply').addEventListener('click', () => {void applyVoiceTranscript();});
        select('beneficiary-voice-clear').addEventListener('click', () => {
            cancelVoice();
            voiceText.value = ''; voiceStatus.textContent = '';
            beneficiaryFields.forEach(field => beneficiaryInputs[field].classList.remove('voice-recognized', 'voice-review'));
            voiceText.focus();
        });
        const clearBeneficiaryEntry = () => {
            form.dataset.beneficiaryEditing = '0';
            form.dispatchEvent(new Event('beneficiary-entry-reset'));
            beneficiaryFields.forEach(field => {
                if (field === 'has_informed_consent') beneficiaryInputs[field].checked = true;
                else beneficiaryInputs[field].value = '';
            });
            beneficiaryInputs.disability.value = 'Ninguna';
            beneficiaryInputs.ethnicity.value = 'Ninguna';
            beneficiaryInputs.nationality.value = @json(\App\Models\Beneficiary::DEFAULT_NATIONALITY);
            syncNationalityOptions(null);
            syncCaregiverRelationshipOptions(null);
            beneficiaryInputs.pregnant_lactating.value = 'Ninguna';
            beneficiaryInputs.is_recurrent.value = '0';
            syncPregnantLactatingField();
            syncCaregiverFields();
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
            ['reporter_email', 'correo electrónico'],
            ['organization', 'organización'],
            ['state_id', 'estado'],
            ['municipality_id', 'municipio'],
            ['parish_id', 'parroquia'],
            ['installation_type', 'tipo de instalación'],
            ['place_name', 'nombre del lugar'],
            ['proyecto_id', 'proyecto'],
            ['sector_proyecto_id', 'sector'],
            ['indicador_proyecto_id', 'indicador']
        ];
        const ensureReportContext = () => {
            if (form.dataset.continuing === '1') return true;
            const missing = requiredHeaderFields.find(([field]) => !form.elements[field].value.trim());
            if (!missing) return true;
            const locationFields = ['state_id', 'municipality_id', 'parish_id', 'installation_type', 'place_name'];
            if (locationFields.includes(missing[0])) {
                setMessage(entryError, 'Antes de guardar, complete la ubicación.');
                (communityLocationToggle.checked ? communityState : placeName).focus();
                return false;
            }
            setMessage(entryError, `Antes de guardar, complete ${missing[1]}.`);
            if (missing[0] === 'indicador_proyecto_id') {
                indicatorCardSearch.focus();
                indicatorCardGrid.scrollIntoView({behavior: 'smooth', block: 'center'});
                return false;
            }
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
            if (beneficiary.has_informed_consent !== '1') {
                return 'Debe confirmar que cuenta con el consentimiento informado del beneficiario.';
            }
            const labels = {
                full_name: 'nombre y apellido',
                age: 'edad',
                sex: 'sexo',
                is_recurrent: 'recurrente'
            };
            const missing = Object.keys(labels).filter(field => String(beneficiary[field] ?? '').trim() === '');
            const errors = missing.length ? [
                `Complete los campos obligatorios: ${missing.map(field => labels[field]).join(', ')}.`
            ] : [];
            const ageRange = selectedIndicatorAgeRange() || {from: 0, to: 120};
            if (beneficiary.age !== '' && (!Number.isInteger(Number(beneficiary.age)) || Number(beneficiary.age) < ageRange.from ||
                    Number(beneficiary.age) > ageRange.to)) errors.push(`La edad debe estar entre ${ageRange.from} y ${ageRange.to} años para el indicador seleccionado.`);
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
                [beneficiary.full_name || 'Sin nombre registrado', beneficiary.has_informed_consent ? 'Sí' : 'No', beneficiary.age, beneficiary.sex, beneficiary.nationality || '—', beneficiary
                    .national_id || '—', beneficiary.phone || '—', beneficiary.disability || 'Ninguna',
                    beneficiary.ethnicity || 'Ninguna', beneficiary.pregnant_lactating || 'Ninguna',
                    beneficiary.is_recurrent ? 'Sí' : 'No'
                ].forEach(value => {
                    const cell = document.createElement('td');
                    cell.textContent = value;
                    row.appendChild(cell);
                });
                const actions = document.createElement('td');
                const actionGroup = document.createElement('div');
                actionGroup.className = 'beneficiary-row-actions';
                const edit = document.createElement('button');
                edit.type = 'button';
                edit.className = 'btn btn-primary btn-icon waves-effect waves-light';
                edit.dataset.beneficiaryId = beneficiary.id;
                edit.title = 'Editar beneficiario';
                edit.setAttribute('aria-label', 'Editar beneficiario');
                edit.innerHTML = '<i class="ri-pencil-line" aria-hidden="true"></i>';
                edit.addEventListener('click', () => {
                    form.dataset.beneficiaryEditing = '1';
                    form.dispatchEvent(new Event('beneficiary-entry-reset'));
                    syncNationalityOptions(beneficiary.nationality);
                    syncCaregiverRelationshipOptions(beneficiary.caregiver_relationship);
                    beneficiaryFields.forEach(field => {
                        if (field === 'has_informed_consent') beneficiaryInputs[field].checked = Boolean(beneficiary[field]);
                        else beneficiaryInputs[field].value = field === 'is_recurrent'
                            ? (beneficiary[field] ? '1' : '0')
                            : String(beneficiary[field] ?? '');
                    });
                    syncPregnantLactatingField();
                    syncCaregiverFields();
                    beneficiaryEditId = beneficiary.id;
                    select('beneficiary-entry-title').textContent = `Editar beneficiario ${index + 1}`;
                    saveButton.textContent = 'Actualizar beneficiario';
                    select('cancel-beneficiary-edit').hidden = false;
                    select('beneficiary_full_name').focus();
                });
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn btn-danger btn-icon waves-effect waves-light';
                remove.title = 'Eliminar beneficiario';
                remove.setAttribute('aria-label', 'Eliminar beneficiario');
                remove.innerHTML = '<i class="ri-delete-bin-5-line" aria-hidden="true"></i>';
                remove.addEventListener('click', () => removeBeneficiary(beneficiary));
                @can('editar beneficiarios')
                @if(!$continuing)
                actionGroup.append(edit);
                @endif
                @endcan
                @can('eliminar beneficiarios')
                @if(!$continuing)
                actionGroup.append(remove);
                @endif
                @endcan
                actions.appendChild(actionGroup);
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
        const recurrence = window.BeneficiaryRecurrence.create({
            form, inputs: beneficiaryInputs, project, status: recurrenceWarning,
            url: @json(route('beneficiaries.project-recurrence')),
            csrf: document.querySelector('meta[name="csrf-token"]').content,
        });
        const checkPossibleRecurrence = () => recurrence.check();
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
            if (form.dataset.beneficiaryLookupPending === '1' || form.dataset.beneficiaryVoicePending === '1') {
                setMessage(entryError, 'Espere a que termine el dictado y la consulta de la cédula antes de guardar.');
                return;
            }
            if (!await validateCoordinates()) {
                setMessage(entryError, 'Corrija las coordenadas GPS antes de guardar el beneficiario.');
                (latitudeInput.value.trim() ? longitudeInput : latitudeInput).focus();
                return;
            }
            const beneficiary = beneficiaryRecord();
            if (!await checkPossibleRecurrence()) {
                setMessage(entryError, 'No se pudo verificar la recurrencia. Revise los datos y vuelva a guardar.');
                return;
            }
            if (isSaving) return;
            beneficiary.is_recurrent = beneficiaryInputs.is_recurrent.value;
            const validationMessage = beneficiaryValidationMessage(beneficiary);
            if (validationMessage) {
                setMessage(entryError, validationMessage);
                return;
            }
            const signature = headerSignature(),
                headersChanged = Boolean(activeReportId && activeHeaderSignature !== signature),
                createsNewReport = form.dataset.continuing !== '1' && (!activeReportId || headersChanged);
            if (form.dataset.reportUpdateUrl && headersChanged) {
                setMessage(entryError, 'Guarde primero los cambios del registro antes de guardar o editar beneficiarios.');
                return;
            }
            if (beneficiaryEditId && createsNewReport && !form.dataset.beneficiaryUpdateUrl) {
                setMessage(entryError,
                'Para editar, restaure los encabezados con los que se guardó este beneficiario.');
                return;
            }
            let data;
            let url = form.dataset.beneficiaryUrl;
            if (form.dataset.beneficiaryUpdateUrl) {
                data = new FormData(form);
                beneficiaryFields.forEach(field => data.set(`beneficiary[${field}]`, beneficiary[field]));
                data.set('_method', 'PUT');
                url = form.dataset.beneficiaryUpdateUrl;
            } else if (beneficiaryEditId) {
                data = new FormData();
                beneficiaryFields.forEach(field => data.set(field, beneficiary[field]));
                url = `{{ url('/beneficiarios') }}/${beneficiaryEditId}`;
                data.set('_method', 'PUT');
            } else {
                data = new FormData(form);
                beneficiaryFields.forEach(field => data.set(`beneficiary[${field}]`, beneficiary[field]));
                if (!createsNewReport) data.set('report_id', activeReportId);
                if (form.dataset.reportUpdateUrl) storedAssociatedIds.forEach(id => data.append('associated_indicator_ids[]', id));
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
                window.SiaDesktopNotifications?.notify(beneficiaryEditId || form.dataset.beneficiaryUpdateUrl ? 'updated' : 'saved');
                if (form.dataset.beneficiaryUpdateUrl) {
                    window.location.assign(result.report.url);
                    return;
                }
                if (beneficiaryEditId) beneficiaries = beneficiaries.map(item => item.id === result.beneficiary.id ?
                    result.beneficiary : item);
                else beneficiaries = createsNewReport ? [result.beneficiary] : [...beneficiaries, result.beneficiary];
                if (result.report) {
                    activeReportId = result.report.id;
                    activeHeaderSignature = signature;
                    const link = select('current-report-link');
                    link.href = result.report.url;
                    link.hidden = false;
                    if (result.report.resume_url && @json(auth()->user()->can('ver detalle de registros'))) {
                        // Remember the saved list on refresh; a browser history
                        // restriction must not turn a successful save into an error.
                        try { window.history.replaceState(null, '', result.report.resume_url); } catch (_) {}
                    }
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
                window.SiaDesktopNotifications?.notify('error');
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
                window.SiaDesktopNotifications?.notify('deleted');
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
                window.SiaDesktopNotifications?.notify('error');
            }
        }
        saveButton.addEventListener('click', saveBeneficiary);
        select('cancel-beneficiary-edit').addEventListener('click', () => {
            if (form.dataset.beneficiaryUpdateUrl) window.location.assign(@json($editing ? route('reports.show', $report) : route('reports.index')));
            else clearBeneficiaryEntry();
        });
        form.addEventListener('submit', event => event.preventDefault());
        ['full_name', 'national_id'].forEach(field => beneficiaryInputs[field].addEventListener('blur',
            checkPossibleRecurrence));
        beneficiaryInputs.full_name.addEventListener('input', () => {
            const start = beneficiaryInputs.full_name.selectionStart;
            beneficiaryInputs.full_name.value = beneficiaryInputs.full_name.value.toLocaleUpperCase('es-VE');
            beneficiaryInputs.full_name.setSelectionRange(start, start);
        });
        beneficiaryInputs.age.addEventListener('input', syncCaregiverFields);
        beneficiaryInputs.age.addEventListener('change', () => {syncCaregiverFields(); checkPossibleRecurrence();});
        beneficiaryInputs.sex.addEventListener('change', () => {
            syncPregnantLactatingField();
            checkPossibleRecurrence();
        });
        form.addEventListener('beneficiary-autofill', () => {
            syncPregnantLactatingField();
            syncCaregiverFields();
            void checkPossibleRecurrence();
        });
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
        syncCaregiverFields();
        renderBeneficiaries();
        const initialBeneficiaryEditId = @json($editing ? $editBeneficiaryId : null);
        if (initialBeneficiaryEditId) {
            const initialEditButton = beneficiaryList.querySelector(`[data-beneficiary-id="${initialBeneficiaryEditId}"]`);
            if (initialEditButton) {
                initialEditButton.click();
            }
        }

        const saveReportChanges = select('save-report-changes');
        if (saveReportChanges) saveReportChanges.addEventListener('click', async () => {
            if (form.dataset.beneficiaryUpdateUrl) {
                await saveBeneficiary();
                return;
            }
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
                window.SiaDesktopNotifications?.notify('updated');
                ['evidence_1', 'evidence_2', 'evidence_3'].forEach(field => {
                    if (form.elements[field]) form.elements[field].value = '';
                });
                setMessage(entrySuccess, result.message);
            } catch (error) {
                setMessage(entryError, error.message);
                window.SiaDesktopNotifications?.notify('error');
            } finally {
                isSaving = false;
                saveReportChanges.disabled = false;
            }
        });
    </script>
    <script src="{{ asset('js/beneficiary-id-autofill.js') }}?v={{ filemtime(public_path('js/beneficiary-id-autofill.js')) }}" defer></script>
@endpush
