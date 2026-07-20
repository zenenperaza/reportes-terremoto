@extends('layouts.app')

@section('title', 'Registros | Respuesta ASONACOP')

@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">{{ $isCoordinator ? 'Consolidado de respuesta' : 'Historial personal' }}</p>
        <h1>Registros de actividades</h1>
        <p class="muted">Filtre por ubicación, fecha o estado para localizar rápidamente un registro.</p>
    </div>
    <div class="heading-actions">
        @if ($isCoordinator)<a class="button button-secondary" href="{{ route('reports.export', request()->query()) }}">Exportar CSV</a>@endif
        <a class="button button-primary" href="{{ route('reports.create') }}">+ Nuevo registro</a>
    </div>
</section>

<section class="content-card filter-card">
    <form method="get" class="filters">
        <label>Estado
            <select name="state_id"><option value="">Todos</option>@foreach($states as $state)<option value="{{ $state->id }}" @selected(($filters['state_id'] ?? '') == $state->id)>{{ $state->name }}</option>@endforeach</select>
        </label>
        <label>Desde<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label>Hasta<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <label>Estado del registro
            <select name="status"><option value="">Todos</option><option value="submitted" @selected(($filters['status'] ?? '') === 'submitted')>Enviado</option><option value="reviewed" @selected(($filters['status'] ?? '') === 'reviewed')>Revisado</option></select>
        </label>
        <button class="button button-secondary" type="submit">Aplicar filtros</button>
    </form>
</section>

<section class="content-card">
    @if ($reports->isEmpty())
        <div class="empty-state"><p>No hay registros que coincidan con los filtros.</p></div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Fecha</th>@if($isCoordinator)<th>Registrado por</th>@endif<th>Ubicación</th><th>Actividad</th><th>Beneficiarios</th><th>Estado</th><th></th></tr></thead>
            <tbody>@foreach($reports as $report)
                <tr>
                    <td>{{ $report->report_date->format('d/m/Y') }}</td>
                    @if($isCoordinator)<td>{{ $report->reporter_first_name }} {{ $report->reporter_last_name }}<br><small>{{ $report->organization }}</small></td>@endif
                    <td>{{ $report->state->name }}<br><small>{{ $report->municipality->name }}, {{ $report->parish->name }}</small></td>
                    <td>{{ $report->sector->name }}<br><small>{{ \Illuminate\Support\Str::limit($report->activity->title, 72) }}</small></td>
                    <td>{{ number_format($report->total_beneficiaries) }}</td>
                    <td><span class="status status-{{ $report->status }}">{{ $report->status === 'reviewed' ? 'Revisado' : 'Enviado' }}</span></td>
                    <td><a href="{{ route('reports.show', $report) }}">Ver</a></td>
                </tr>
            @endforeach</tbody>
        </table></div>
        <div class="pagination">{{ $reports->links() }}</div>
    @endif
</section>
@endsection
