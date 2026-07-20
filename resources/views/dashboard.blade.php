@extends('layouts.app')

@section('title', 'Panel | Respuesta UNICEF')

@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">{{ $isCoordinator ? 'Vista de coordinación' : 'Mi espacio de registros' }}</p>
        <h1>{{ $isCoordinator ? 'Seguimiento de la respuesta' : 'Mis actividades registradas' }}</h1>
        <p class="muted">{{ $isCoordinator ? 'Consolide los registros recibidos y revise sus evidencias.' : 'Cada registro conserva sus datos, desagregación y soportes.' }}</p>
    </div>
    <a class="button button-primary" href="{{ route('reports.create') }}">Registrar actividad</a>
</section>

<section class="stats-grid" aria-label="Resumen">
    <article class="stat-card"><span>Registros</span><strong>{{ number_format($reportCount) }}</strong></article>
    <article class="stat-card"><span>Beneficiarios alcanzados</span><strong>{{ number_format($beneficiaryTotal) }}</strong></article>
    <article class="stat-card"><span>Pendientes de revisión</span><strong>{{ number_format($submittedCount) }}</strong></article>
    <article class="stat-card"><span>Revisados</span><strong>{{ number_format($reviewedCount) }}</strong></article>
</section>

<section class="content-card">
    <div class="card-heading"><h2>Registros recientes</h2><a href="{{ route('reports.index') }}">Ver todos</a></div>
    @if ($recentReports->isEmpty())
        <div class="empty-state"><p>Aún no se han registrado actividades.</p><a class="button button-primary" href="{{ route('reports.create') }}">Crear el primer registro</a></div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Fecha</th><th>Ubicación</th><th>Sector</th><th>Beneficiarios</th><th>Estado</th><th></th></tr></thead>
            <tbody>@foreach ($recentReports as $report)
                <tr>
                    <td>{{ $report->report_date->format('d/m/Y') }}</td>
                    <td>{{ $report->state->name }} · {{ $report->municipality->name }}</td>
                    <td>{{ $report->sector->name }}</td>
                    <td>{{ number_format($report->total_beneficiaries) }}</td>
                    <td><span class="status status-{{ $report->status }}">{{ $report->status === 'reviewed' ? 'Revisado' : 'Enviado' }}</span></td>
                    <td><a href="{{ route('reports.show', $report) }}">Abrir</a></td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif
</section>
@endsection
