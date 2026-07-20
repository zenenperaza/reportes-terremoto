@extends('layouts.app')

@section('title', 'Registro #'.$report->id.' | Respuesta ASONACOP')

@section('content')
<section class="page-heading detail-heading">
    <div>
        <p class="eyebrow">Registro #{{ $report->id }} · {{ $report->report_date->format('d/m/Y') }}</p>
        <h1>{{ $report->sector->name }}</h1>
        <p class="muted">{{ $report->activity->title }}</p>
    </div>
    <div class="heading-actions"><span class="status status-{{ $report->status }}">{{ $report->status === 'reviewed' ? 'Revisado' : 'Enviado' }}</span><a class="button button-secondary" href="{{ route('reports.index') }}">Volver a registros</a></div>
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
        <div class="table-wrap"><table class="beneficiary-table"><thead><tr><th>Nombre y apellido</th><th>Edad</th><th>Sexo</th><th>Cédula</th><th>Teléfono</th><th>Discapacidad</th><th>Indígena</th><th>Emb./lact.</th><th>Recurrente</th></tr></thead><tbody>@foreach($report->beneficiaries as $beneficiary)<tr><td>{{ $beneficiary->full_name }}</td><td>{{ $beneficiary->age }}</td><td>{{ $beneficiary->sex }}</td><td>{{ $beneficiary->national_id ?: '—' }}</td><td>{{ $beneficiary->phone ?: '—' }}</td><td>{{ $beneficiary->disability }}</td><td>{{ $beneficiary->ethnicity }}</td><td>{{ $beneficiary->pregnant_lactating }}</td><td>{{ $beneficiary->is_recurrent ? 'Sí' : 'No' }}</td></tr>@endforeach</tbody></table></div>
    @endif
</section>

<section class="content-card"><h2>Registro cualitativo</h2><p class="notes">{{ $report->qualitative_notes ?: 'No se registraron notas cualitativas.' }}</p><h3>Medios de verificación</h3>@if($report->evidences->isEmpty())<p class="muted">No se adjuntaron medios de verificación.</p>@else<div class="evidence-list">@foreach($report->evidences as $evidence)<a href="{{ route('evidences.download', $evidence) }}">Soporte {{ $evidence->slot }} · {{ $evidence->original_name }} <small>({{ number_format($evidence->size / 1024, 0) }} KB)</small></a>@endforeach</div>@endif</section>
@endsection
