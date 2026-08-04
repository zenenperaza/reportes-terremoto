@extends('layouts.app')

@section('title', 'Registro #'.$report->id.' | Respuesta ASONACOP')

@section('content')
<section class="page-heading detail-heading">
    <div>
        <p class="eyebrow">Registro #{{ $report->id }} · {{ $report->report_date->format('d/m/Y') }}</p>
        <h1>{{ $report->sector->name }}</h1>
        <p class="muted">{{ $report->activity->title }}</p>
    </div>
    <div class="heading-actions">
        <span class="status status-{{ $report->status }}">{{ $report->status === 'reviewed' ? 'Revisado' : 'Enviado' }}</span>
        @if($canEditBeneficiaries)<a class="button button-primary" href="{{ route('reports.edit', $report) }}">Editar registro</a>@endif
        @if($canDeleteReport)
            <form method="post" action="{{ route('reports.destroy', $report) }}" class="report-delete-form">
                @csrf
                @method('DELETE')
                <button class="button button-danger" type="submit">Eliminar registro</button>
            </form>
        @else
            <button class="button button-danger report-delete-authorization" type="button">Eliminar registro</button>
        @endif
        <a class="button button-secondary" href="{{ route('reports.index') }}">Volver a registros</a>
    </div>
</section>

@if($isCoordinator && $report->status !== 'reviewed')
    <form method="post" action="{{ route('reports.review', $report) }}" class="review-banner">@csrf<span>Confirme cuando haya comprobado los datos y evidencias del registro.</span><button class="button button-small" type="submit">Marcar como revisado</button></form>
@endif

@push('styles')
    <style>
        .button-danger { background: var(--danger); color: #fff; }
        .button-danger:hover { background: #8f1e15; color: #fff; }
        .heading-actions form { margin: 0; }

        @media (max-width: 1500px) {
            .beneficiary-table { min-width: 0; }
            .beneficiary-table,
            .beneficiary-table tbody { display: block; width: 100%; }
            .beneficiary-table thead { display: none; }
            .beneficiary-table tbody { display: grid; gap: 14px; }
            .beneficiary-table tr {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                overflow: hidden;
                border: 1px solid var(--line);
                border-radius: 7px;
            }
            .beneficiary-table td,
            .beneficiary-table td:first-child,
            .beneficiary-table td:last-child {
                display: grid;
                grid-template-columns: minmax(120px, 42%) minmax(0, 1fr);
                gap: 10px;
                min-width: 0;
                padding: 10px 12px;
                border-bottom: 1px solid #e8eef1;
                overflow-wrap: anywhere;
            }
            .beneficiary-table td::before {
                content: attr(data-label);
                color: var(--muted);
                font-size: 12px;
                font-weight: 700;
                letter-spacing: .03em;
                text-transform: uppercase;
            }
            .beneficiary-table td[data-label="Acciones"] {
                grid-column: 1 / -1;
                display: flex;
                align-items: center;
                border-bottom: 0;
            }
            .beneficiary-table td[data-label="Acciones"]::before { flex: 0 0 120px; }
            .beneficiary-table td[data-label="Acciones"] .table-action + .table-action { margin-left: 8px; }
        }

        @media (max-width: 640px) {
            .beneficiary-table tr { grid-template-columns: 1fr; }
            .beneficiary-table td,
            .beneficiary-table td:first-child,
            .beneficiary-table td:last-child { grid-template-columns: 1fr; gap: 3px; }
            .beneficiary-table td[data-label="Acciones"] { display: grid; }
            .beneficiary-table td[data-label="Acciones"]::before { flex-basis: auto; }
            .beneficiary-table td[data-label="Acciones"] .table-action + .table-action { margin-left: 0; }
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (() => {
            const authorizationButton = document.querySelector('.report-delete-authorization');
            const deleteForm = document.querySelector('.report-delete-form');
            const showAuthorizationAlert = () => {
                const options = {
                    icon: 'warning',
                    title: 'Autorizaci\u00f3n requerida',
                    text: 'Solicite autorizaci\u00f3n a un administrador para eliminar este registro.',
                    confirmButtonText: 'Entendido',
                };

                if (typeof Swal !== 'undefined') {
                    Swal.fire(options);
                } else {
                    window.alert(`${options.title}: ${options.text}`);
                }
            };

            authorizationButton?.addEventListener('click', showAuthorizationAlert);

            deleteForm?.addEventListener('submit', (event) => {
                event.preventDefault();
                const options = {
                    icon: 'warning',
                    title: 'Eliminar registro',
                    text: 'Esta acci\u00f3n eliminar\u00e1 el registro, sus beneficiarios y evidencias. No se puede deshacer.',
                    showCancelButton: true,
                    confirmButtonText: 'S\u00ed, eliminar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#b42318',
                };

                if (typeof Swal === 'undefined') {
                    if (window.confirm('Eliminar este registro? Esta acci\u00f3n no se puede deshacer.')) deleteForm.submit();
                    return;
                }

                Swal.fire(options).then((result) => {
                    if (result.isConfirmed) deleteForm.submit();
                });
            });
        })();
    </script>
@endpush

<div class="details-grid">
    <section class="content-card"><h2>Quién implementa</h2><dl class="detail-list"><div><dt>Persona que registra</dt><dd>{{ $report->reporter_first_name }} {{ $report->reporter_last_name }}</dd></div><div><dt>Correo</dt><dd>{{ $report->reporter_email }}</dd></div><div><dt>Organización</dt><dd>{{ $report->organization }}{{ $report->other_organization ? ' · '.$report->other_organization : '' }}</dd></div></dl></section>
    <section class="content-card"><h2>Dónde</h2><dl class="detail-list"><div><dt>Ubicación</dt><dd>{{ $report->state->name }}, {{ $report->municipality->name }}, {{ $report->parish->name }}</dd></div><div><dt>Instalación</dt><dd>{{ $report->installation_type }}</dd></div><div><dt>Lugar</dt><dd>{{ $report->place_name }}</dd></div>@if($report->latitude !== null)<div><dt>GPS</dt><dd>{{ $report->latitude }}, {{ $report->longitude }} @if($report->gps_accuracy) · precisión {{ $report->gps_accuracy }} m @endif</dd></div>@endif</dl></section>
    <section class="content-card"><h2>Actividad</h2><dl class="detail-list"><div><dt>Sector</dt><dd>{{ $report->sector->name }}</dd></div><div><dt>Actividad</dt><dd>{{ $report->activity->title }}</dd></div><div><dt>Detalles</dt><dd>{{ $report->activity_details ?: 'Sin detalles adicionales.' }}</dd></div><div><dt>Recurrencia</dt><dd>{{ $report->recurrence_status === 'mixto' ? 'Mixta' : ($report->recurrence_status === 'recurrente' ? 'Todos recurrentes' : 'Todos no recurrentes') }}</dd></div></dl></section>
    <section class="content-card"><h2>Grupos con necesidades específicas</h2><dl class="detail-list"><div><dt>Personas con discapacidad</dt><dd>{{ number_format($report->people_with_disabilities) }}</dd></div><div><dt>Población indígena</dt><dd>{{ number_format($report->indigenous_people) }}</dd></div><div><dt>Embarazadas o en lactancia</dt><dd>{{ number_format($report->pregnant_or_lactating_women) }}</dd></div></dl></section>
</div>

<section class="content-card">
    <div class="card-heading"><div><h2>Beneficiarios registrados</h2><p class="muted">Cada fila corresponde a una persona registrada.</p></div><strong class="beneficiary-number">{{ number_format($report->total_beneficiaries) }}</strong></div>
    @if($report->beneficiaries->isEmpty())
        <p class="muted">Este registro fue creado antes del registro individual de beneficiarios.</p>
    @else
        <div class="table-wrap">
            <table class="beneficiary-table">
                <thead><tr><th>Nombre y apellido</th><th>Edad</th><th>Sexo</th><th>Cédula</th><th>Teléfono</th><th>Discapacidad</th><th>Indígena</th><th>Emb./lact.</th><th>Recurrente</th><th>Reportado</th><th>Fecha de reporte</th>@if($canEditBeneficiaries)<th>Acciones</th>@endif</tr></thead>
                <tbody>@foreach($report->beneficiaries as $beneficiary)<tr><td data-label="Nombre y apellido">{{ $beneficiary->full_name ?: 'Sin nombre registrado' }}</td><td data-label="Edad">{{ $beneficiary->age }}</td><td data-label="Sexo">{{ $beneficiary->sex }}</td><td data-label="C&eacute;dula">{{ $beneficiary->national_id ?: '—' }}</td><td data-label="Tel&eacute;fono">{{ $beneficiary->phone ?: '—' }}</td><td data-label="Discapacidad">{{ $beneficiary->disability ?: 'Ninguna' }}</td><td data-label="Ind&iacute;gena">{{ $beneficiary->ethnicity ?: 'Ninguna' }}</td><td data-label="Emb./lact.">{{ $beneficiary->pregnant_lactating ?: 'Ninguna' }}</td><td data-label="Recurrente">{{ $beneficiary->is_recurrent ? 'Sí' : 'No' }}</td><td data-label="Reportado">{{ $beneficiary->reported_at ? 'Sí' : 'No' }}</td><td data-label="Fecha de reporte">{{ $beneficiary->reported_at?->format('d/m/Y') ?: '—' }}</td>@if($canEditBeneficiaries)<td data-label="Acciones"><button class="table-action beneficiary-edit-button" type="button" data-beneficiary-id="{{ $beneficiary->id }}">Editar</button><button class="table-action danger-action beneficiary-delete-button" type="button" data-beneficiary-id="{{ $beneficiary->id }}">Eliminar</button></td>@endif</tr>@endforeach</tbody>
            </table>
        </div>
        @if($canEditBeneficiaries)
            <form id="beneficiary-edit-form" class="beneficiary-entry beneficiary-detail-editor" hidden>
                <div class="card-heading"><div><h2>Editar beneficiario</h2><p class="muted">Actualice los datos y guarde los cambios.</p></div><button type="button" class="button button-secondary" id="cancel-beneficiary-edit">Cancelar</button></div>
                <div class="form-grid beneficiary-form-grid">
                    <label>Nombre y apellido<input name="full_name" maxlength="150"></label>
                    <label>Edad *<input name="age" type="number" min="0" max="120" required></label>
                    <label>Sexo *<select name="sex" required>@foreach($beneficiaryOptions['sexes'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></label>
                    <label>Cédula<input name="national_id" maxlength="30"></label>
                    <label>Teléfono<input name="phone" maxlength="30"></label>
                    <label>Discapacidad<select name="disability">@foreach($beneficiaryOptions['disabilities'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></label>
                    <label>Indígena / etnia<select name="ethnicity">@foreach($beneficiaryOptions['ethnicities'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></label>
                    <label>Embarazada o lactante<select name="pregnant_lactating">@foreach($beneficiaryOptions['pregnant_lactating'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach</select></label>
                    <label>Recurrente *<select name="is_recurrent" required><option value="0">No</option><option value="1">Sí</option></select></label>
                </div>
                <div class="beneficiary-entry-actions"><p id="beneficiary-edit-error" class="field-error" role="alert" hidden></p><button class="button button-primary" type="submit">Guardar cambios</button></div>
            </form>
        @endif
    @endif
</section>

<section class="content-card"><h2>Registro cualitativo</h2><p class="notes">{{ $report->qualitative_notes ?: 'No se registraron notas cualitativas.' }}</p><h3>Medios de verificación</h3>@if($report->evidences->isEmpty())<p class="muted">No se adjuntaron medios de verificación.</p>@else<div class="evidence-list">@foreach($report->evidences as $evidence)<a href="{{ route('evidences.download', $evidence) }}">Soporte {{ $evidence->slot }} · {{ $evidence->original_name }} <small>({{ number_format($evidence->size / 1024, 0) }} KB)</small></a>@endforeach</div>@endif</section>
@endsection

@if($canEditBeneficiaries && $report->beneficiaries->isNotEmpty())
    @push('styles')
        <style>.beneficiary-detail-editor{margin-top:22px}.beneficiary-detail-editor[hidden]{display:none}</style>
    @endpush
    @push('scripts')
        <script>
            (() => {
                const beneficiaries = @json($beneficiaryEditData);
                const form = document.getElementById('beneficiary-edit-form');
                const error = document.getElementById('beneficiary-edit-error');
                let beneficiaryId = null;

                document.querySelectorAll('.beneficiary-edit-button').forEach((button) => button.addEventListener('click', () => {
                    beneficiaryId = button.dataset.beneficiaryId;
                    const beneficiary = beneficiaries[beneficiaryId];
                    Object.entries(beneficiary).forEach(([name, value]) => {
                        const field = form.elements.namedItem(name);
                        if (field) field.value = name === 'is_recurrent' ? (value ? '1' : '0') : (value ?? '');
                    });
                    error.hidden = true;
                    form.hidden = false;
                    form.scrollIntoView({behavior: 'smooth', block: 'center'});
                }));

                document.querySelectorAll('.beneficiary-delete-button').forEach((button) => button.addEventListener('click', async () => {
                    const confirmation = typeof Swal !== 'undefined'
                        ? await Swal.fire({
                            icon: 'warning',
                            title: 'Eliminar beneficiario',
                            text: 'Esta acci\u00f3n no se puede deshacer.',
                            showCancelButton: true,
                            confirmButtonText: 'S\u00ed, eliminar',
                            cancelButtonText: 'Cancelar',
                            confirmButtonColor: '#b42318',
                        })
                        : {isConfirmed: window.confirm('Eliminar este beneficiario? Esta acci\u00f3n no se puede deshacer.')};

                    if (!confirmation.isConfirmed) return;

                    button.disabled = true;
                    try {
                        const response = await fetch(`{{ url('/beneficiarios') }}/${button.dataset.beneficiaryId}`, {
                            method: 'DELETE',
                            headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                        });
                        const data = await response.json();

                        if (!response.ok) throw new Error(data.message || 'No se pudo eliminar el beneficiario.');

                        if (data.report_deleted) {
                            window.location.assign(`{{ route('reports.index') }}`);
                            return;
                        }

                        window.location.reload();
                    } catch (exception) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({icon: 'error', title: 'No se pudo eliminar', text: exception.message});
                        } else {
                            window.alert(exception.message);
                        }
                        button.disabled = false;
                    }
                }));

                document.getElementById('cancel-beneficiary-edit').addEventListener('click', () => {
                    form.hidden = true;
                    beneficiaryId = null;
                });

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    if (!form.reportValidity() || !beneficiaryId) return;
                    const submitButton = form.querySelector('[type="submit"]');
                    submitButton.disabled = true;
                    error.hidden = true;
                    try {
                        const response = await fetch(`{{ url('/beneficiarios') }}/${beneficiaryId}`, {
                            method: 'PUT',
                            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                            body: JSON.stringify(Object.fromEntries(new FormData(form))),
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            const validationMessage = data.errors ? Object.values(data.errors).flat()[0] : null;
                            throw new Error(validationMessage || data.message || 'No se pudo actualizar el beneficiario.');
                        }
                        window.location.reload();
                    } catch (exception) {
                        error.textContent = exception.message;
                        error.hidden = false;
                    } finally {
                        submitButton.disabled = false;
                    }
                });
            })();
        </script>
    @endpush
@endif
