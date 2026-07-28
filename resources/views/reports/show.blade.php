@extends('layouts.app')

@section('title', 'Registro #'.$report->id.' | Respuesta ASONACOP')

@section('content')
<section class="page-heading detail-heading">
    <div>
        <p class="eyebrow">Registro #{{ $report->id }} · {{ $report->report_date->format('d/m/Y') }}</p>
        <h1>{{ $report->sector->name }}</h1>
        <p class="muted">{{ $report->activity->title }}</p>
    </div>
    <div class="heading-actions"><span class="status status-{{ $report->status }}">{{ $report->status === 'reviewed' ? 'Revisado' : 'Enviado' }}</span>@if($canEditBeneficiaries)<a class="button button-primary" href="{{ route('reports.edit', $report) }}">Editar registro</a>@endif<a class="button button-secondary" href="{{ route('reports.index') }}">Volver a registros</a></div>
</section>

@if($isCoordinator && $report->status !== 'reviewed')
    <form method="post" action="{{ route('reports.review', $report) }}" class="review-banner">@csrf<span>Confirme cuando haya comprobado los datos y evidencias del registro.</span><button class="button button-small" type="submit">Marcar como revisado</button></form>
@endif

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
                <tbody>@foreach($report->beneficiaries as $beneficiary)<tr><td>{{ $beneficiary->full_name }}</td><td>{{ $beneficiary->age }}</td><td>{{ $beneficiary->sex }}</td><td>{{ $beneficiary->national_id ?: '—' }}</td><td>{{ $beneficiary->phone ?: '—' }}</td><td>{{ $beneficiary->disability ?: 'Ninguna' }}</td><td>{{ $beneficiary->ethnicity ?: 'Ninguna' }}</td><td>{{ $beneficiary->pregnant_lactating ?: 'Ninguna' }}</td><td>{{ $beneficiary->is_recurrent ? 'Sí' : 'No' }}</td><td>{{ $beneficiary->reported_at ? 'Sí' : 'No' }}</td><td>{{ $beneficiary->reported_at?->format('d/m/Y') ?: '—' }}</td>@if($canEditBeneficiaries)<td><a class="table-action" href="{{ route('reports.edit', ['report' => $report, 'beneficiary' => $beneficiary->id]) }}">Editar</a></td>@endif</tr>@endforeach</tbody>
            </table>
        </div>
        @if($canEditBeneficiaries)
            <form id="beneficiary-edit-form" class="beneficiary-entry beneficiary-detail-editor" hidden>
                <div class="card-heading"><div><h2>Editar beneficiario</h2><p class="muted">Actualice los datos y guarde los cambios.</p></div><button type="button" class="button button-secondary" id="cancel-beneficiary-edit">Cancelar</button></div>
                <div class="form-grid beneficiary-form-grid">
                    <label>Nombre y apellido *<input name="full_name" maxlength="150" required></label>
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
