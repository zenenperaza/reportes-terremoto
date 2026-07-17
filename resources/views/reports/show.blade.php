@extends('layouts.app')

@section('title', 'Reporte #'.$report->id.' | Respuesta UNICEF')

@section('content')
@php
    $schemeKey = $report->beneficiary_breakdown['scheme'] ?? 'tradicional';
    $scheme = $breakdownSchemes[$schemeKey] ?? $breakdownSchemes['tradicional'];
@endphp
<section class="page-heading detail-heading">
    <div>
        <p class="eyebrow">Reporte #{{ $report->id }} · {{ $report->report_date->format('d/m/Y') }}</p>
        <h1>{{ $report->sector->name }}</h1>
        <p class="muted">{{ $report->activity->title }}</p>
    </div>
    <div class="heading-actions"><span class="status status-{{ $report->status }}">{{ $report->status === 'reviewed' ? 'Revisado' : 'Enviado' }}</span><a class="button button-secondary" href="{{ route('reports.index') }}">Volver a reportes</a></div>
</section>

@if($isCoordinator && $report->status !== 'reviewed')
    <form method="post" action="{{ route('reports.review', $report) }}" class="review-banner">@csrf<span>Confirme cuando haya comprobado los datos y evidencias del reporte.</span><button class="button button-small" type="submit">Marcar como revisado</button></form>
@endif

<div class="details-grid">
    <section class="content-card"><h2>Quién implementa</h2><dl class="detail-list"><div><dt>Persona que reporta</dt><dd>{{ $report->reporter_first_name }} {{ $report->reporter_last_name }}</dd></div><div><dt>Correo</dt><dd>{{ $report->reporter_email }}</dd></div><div><dt>Organización</dt><dd>{{ $report->organization }}{{ $report->other_organization ? ' · '.$report->other_organization : '' }}</dd></div></dl></section>
    <section class="content-card"><h2>Dónde</h2><dl class="detail-list"><div><dt>Ubicación</dt><dd>{{ $report->state->name }}, {{ $report->municipality->name }}, {{ $report->parish->name }}</dd></div><div><dt>Instalación</dt><dd>{{ $report->installation_type }}</dd></div><div><dt>Lugar</dt><dd>{{ $report->place_name }}</dd></div>@if($report->latitude !== null)<div><dt>GPS</dt><dd>{{ $report->latitude }}, {{ $report->longitude }} @if($report->gps_accuracy) · precisión {{ $report->gps_accuracy }} m @endif</dd></div>@endif</dl></section>
    <section class="content-card"><h2>Actividad</h2><dl class="detail-list"><div><dt>Sector</dt><dd>{{ $report->sector->name }}</dd></div><div><dt>Actividad</dt><dd>{{ $report->activity->title }}</dd></div><div><dt>Detalles</dt><dd>{{ $report->activity_details ?: 'Sin detalles adicionales.' }}</dd></div><div><dt>Beneficiarios</dt><dd>{{ $report->recurrence_status === 'recurrente' ? 'Recurrentes' : 'No recurrentes' }}</dd></div></dl></section>
    <section class="content-card"><h2>Grupos con necesidades específicas</h2><dl class="detail-list"><div><dt>Personas con discapacidad</dt><dd>{{ number_format($report->people_with_disabilities) }}</dd></div><div><dt>Población indígena</dt><dd>{{ number_format($report->indigenous_people) }}</dd></div><div><dt>Embarazadas o en lactancia</dt><dd>{{ number_format($report->pregnant_or_lactating_women) }}</dd></div></dl></section>
</div>

<section class="content-card">
    <div class="card-heading"><div><h2>Beneficiarios alcanzados</h2><p class="muted">{{ $scheme['label'] }}</p></div><strong class="beneficiary-number">{{ number_format($report->total_beneficiaries) }}</strong></div>
    <div class="breakdown-display">@foreach($scheme['fields'] as $key => $label)<div><span>{{ $label }}</span><strong>{{ number_format((int) ($report->beneficiary_breakdown[$key] ?? 0)) }}</strong></div>@endforeach</div>
</section>

<section class="content-card"><h2>Reporte cualitativo</h2><p class="notes">{{ $report->qualitative_notes ?: 'No se registraron notas cualitativas.' }}</p><h3>Medios de verificación</h3>@if($report->evidences->isEmpty())<p class="muted">No se adjuntaron medios de verificación.</p>@else<div class="evidence-list">@foreach($report->evidences as $evidence)<a href="{{ route('evidences.download', $evidence) }}">Soporte {{ $evidence->slot }} · {{ $evidence->original_name }} <small>({{ number_format($evidence->size / 1024, 0) }} KB)</small></a>@endforeach</div>@endif</section>
@endsection
