@extends('layouts.app')
@section('title', 'Listados de registros | SIA')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/report-datatable.css') }}?v={{ filemtime(public_path('css/report-datatable.css')) }}">
@endpush
@section('content')
<section class="page-heading">
    <div><p class="eyebrow">Registros · Listados guardados</p><h1>Listados de registros</h1>
        <p class="muted">Cada fila corresponde a un listado guardado, no a un beneficiario. Abra «Ver listado» para consultar sus personas y continuar agregando cuando esté permitido.</p></div>
    <div class="heading-actions">
        <a class="button button-secondary" href="{{ route('reports.index', array_diff_key($filters, ['q' => true])) }}">Volver a registros</a>
        @can('registrar actividad')<a class="button button-primary" href="{{ route('reports.create') }}">+ Nuevo registro</a>@endcan
    </div>
</section>
<section class="content-card filter-card report-list-filter-card" aria-label="Filtros de listados">
    @if(filled($filters['servicio_actividad_id'] ?? null))
        <p class="muted">Se conserva el filtro de servicio seleccionado en Registros.
            <a href="{{ route('reports.lists', array_diff_key($filters, ['servicio_actividad_id' => true])) }}">Quitar filtro de servicio</a></p>
    @endif
    <form method="get" action="{{ route('reports.lists') }}" class="filters report-list-filters">
        @if(filled($filters['servicio_actividad_id'] ?? null))<input type="hidden" name="servicio_actividad_id" value="{{ $filters['servicio_actividad_id'] }}">@endif
        <label class="report-filter-field report-filter-project" for="records-project-filter">Proyectos
            <select id="records-project-filter" name="project_ids[]" multiple data-record-project-filter data-placeholder="Todos los proyectos">
                <option value="__all__" data-project-select-all disabled>Seleccionar todos los proyectos</option>
                @foreach($projectOptions as $project)<option value="{{ $project->id }}" @selected(in_array($project->id, $filters['project_ids'] ?? [], true))>{{ $project->codigo }} — {{ $project->nombre_alias ?: $project->descripcion }}</option>@endforeach
            </select><small>Sin selección se incluyen todos los proyectos disponibles para su usuario.</small>
        </label>
        <label class="report-filter-field report-filter-reported">Estado de reporte
            <select name="reported"><option value="">Todos</option><option value="1" @selected(($filters['reported'] ?? '') === '1')>Sí</option><option value="0" @selected(($filters['reported'] ?? '') === '0')>No</option></select>
        </label>
        <div class="report-filter-field report-filter-period">@include('reports.partials.period-filter')</div>
        <div class="report-filter-field report-filter-summary">@include('reports.partials.summary-indicator-filter')</div>
        <label class="report-filter-field report-filter-top" for="records-state-filter">Estado
            <select name="state_id[]" id="records-state-filter" multiple>@foreach($states as $state)<option value="{{ $state->id }}" @selected(in_array($state->id, $filters['state_id'] ?? [], true))>{{ $state->name }}</option>@endforeach</select>
        </label>
        <label class="report-filter-field report-filter-date">Fecha de atención desde<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label class="report-filter-field report-filter-date">Fecha de atención hasta<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <label class="report-filter-field report-filter-user">Registrado por
            <select name="user_id"><option value="">Todos los usuarios</option>@foreach($registeringUsers as $user)<option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $user->id)>{{ $user->name }}</option>@endforeach</select>
        </label>
        <label class="report-filter-field report-filter-user">Buscar listado
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="200" placeholder="N.º, proyecto, indicador, lugar o usuario">
        </label>
        <div class="report-filter-actions"><a class="button button-secondary" href="{{ route('reports.lists') }}">Limpiar filtros</a><button class="button button-primary" type="submit">Consultar listados</button></div>
    </form>
</section>
<section class="content-card report-table-card">
    <h2>Listados guardados ({{ number_format($reports->total()) }})</h2>
    <p class="muted">Se conservan los listados originales: no se unen por proyecto, fecha ni indicador. La cantidad indica los beneficiarios individuales que tiene cada listado.</p>
    <div class="table-wrap"><table class="report-saved-lists" style="width:100%"><thead><tr><th>Listado</th><th>Creado</th><th>Período</th><th>Proyecto</th><th>Indicador</th><th>Fecha de atención / Lugar</th><th>Registrado por</th><th>Beneficiarios</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
        @forelse($reports as $report)<tr>
            <td>#{{ $report->id }}</td><td>{{ $report->created_at->format('d/m/Y H:i') }}</td>
            <td>{{ \App\Support\ReportPeriod::label($report->reporting_period) }}</td>
            <td><strong>{{ $report->proyecto?->codigo ?: 'Sin proyecto asociado' }}</strong><br><small>{{ $report->proyecto?->nombre_alias ?: $report->proyecto?->descripcion }}</small></td>
            <td><strong>{{ $report->indicadorProyecto?->indicador?->codigo ?: $report->activity?->code }}</strong><br><small>{{ $report->indicadorProyecto?->indicador?->descripcion ?: $report->activity?->title }}</small></td>
            <td>{{ $report->report_date->format('d/m/Y') }}<br><small>{{ $report->place_name }}</small></td>
            <td>{{ $report->user?->name ?: 'Usuario no disponible' }}</td><td>{{ number_format($report->beneficiaries_count) }}</td>
            <td><span class="status status-{{ $report->status }}">{{ $report->status === 'reviewed' ? 'Revisado' : 'Enviado' }}</span><br>
                <small>{{ $report->beneficiaries_count === 0 ? 'Sin beneficiarios individuales' : ($report->unreported_beneficiaries_count === 0 ? 'Todos reportados' : ($report->unreported_beneficiaries_count === $report->beneficiaries_count ? 'No reportados' : 'Parcialmente reportados')) }}</small></td>
            <td>@can('ver detalle de registros')<a class="button button-secondary button-small" href="{{ route('reports.show', $report) }}">Ver listado</a>@else<span class="muted">Sin permiso de detalle</span>@endcan</td>
        </tr>@empty<tr><td colspan="10">No hay listados que coincidan con los filtros.</td></tr>@endforelse
    </tbody></table></div>
    <div class="pagination">{{ $reports->links() }}</div>
</section>
@endsection
@push('scripts')
<script src="{{ asset('js/record-project-filter.js') }}?v={{ filemtime(public_path('js/record-project-filter.js')) }}" defer></script>
<script src="{{ asset('js/record-state-filter.js') }}?v={{ filemtime(public_path('js/record-state-filter.js')) }}" defer></script>
@endpush
