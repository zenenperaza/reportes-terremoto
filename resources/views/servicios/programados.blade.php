@extends('layouts.app')
@section('title', 'Informes por Servicios | SIA')
@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/datatables/responsive.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/programmed-services.css') }}?v={{ filemtime(public_path('css/programmed-services.css')) }}">
@endpush
@section('content')
<section class="page-heading compact-heading">
    <div>
        <p class="eyebrow">Informes · Servicios</p>
        <h1>Informes por Servicios</h1>
        <p class="muted">Consulte los servicios registrados como entregados a beneficiarios, por proyecto, sector, indicador y actividad.</p>
    </div>
    <div class="heading-actions">
        @if(auth()->user()->isAdministrator())<a class="button button-secondary" href="{{ route('servicios.index') }}">Catálogo de servicios</a>@endif
    </div>
</section>
<section class="content-card programmed-service-filter-card" aria-label="Filtros del informe por servicios">
    <form method="get" action="{{ route('servicios-programados.index') }}" class="programmed-service-filters">
        <label for="programmed_project">Proyecto
            <select id="programmed_project" name="proyecto_id"><option value="">Todos los proyectos</option>@foreach($proyectos as $project)<option value="{{ $project->id }}" @selected((string) $filters['proyecto_id'] === (string) $project->id)>{{ $project->codigo }}{{ $project->nombre_alias ? ' — '.$project->nombre_alias : '' }}</option>@endforeach</select>
        </label>
        <label for="programmed_sector">Sector
            <select id="programmed_sector" name="sector_id"><option value="">Todos los sectores</option>@foreach($sectores as $sector)<option value="{{ $sector->id }}" @selected((string) $filters['sector_id'] === (string) $sector->id)>{{ $sector->name }}</option>@endforeach</select>
        </label>
        <label for="programmed_indicator">Indicador
            <select id="programmed_indicator" name="indicador_id"><option value="">Todos los indicadores</option>@foreach($indicadores as $indicator)<option value="{{ $indicator->id }}" @selected((string) $filters['indicador_id'] === (string) $indicator->id)>{{ $indicator->codigo }} — {{ $indicator->nombre_corto ?: $indicator->descripcion }}</option>@endforeach</select>
        </label>
        <label for="programmed_activity">Actividad
            <select id="programmed_activity" name="actividad_id"><option value="">Todas las actividades</option>@foreach($actividades as $activity)<option value="{{ $activity->id }}" @selected((string) $filters['actividad_id'] === (string) $activity->id)>{{ $activity->codigo }} — {{ $activity->descripcion }}</option>@endforeach</select>
        </label>
        <label for="programmed_service">Servicio
            <select id="programmed_service" name="servicio_id"><option value="">Todos los servicios</option>@foreach($servicios as $service)<option value="{{ $service->id }}" @selected((string) $filters['servicio_id'] === (string) $service->id)>{{ $service->nombre }}</option>@endforeach</select>
        </label>
        <label for="programmed_status">Estado del servicio
            <select id="programmed_status" name="estatus"><option value="">Todos</option><option value="1" @selected((string) $filters['estatus'] === '1')>Activo</option><option value="0" @selected((string) $filters['estatus'] === '0')>Inactivo</option></select>
        </label>
        @include('reports.partials.period-filter')
        <label for="service_attention_from">Fecha de atención desde
            <input type="date" id="service_attention_from" name="from" value="{{ $filters['from'] }}">
        </label>
        <label for="service_attention_to">Fecha de atención hasta
            <input type="date" id="service_attention_to" name="to" value="{{ $filters['to'] }}">
        </label>
        <div class="programmed-service-filter-actions">
            <a class="button button-secondary" href="{{ route('servicios-programados.index') }}">Limpiar filtros</a>
            <button class="button button-primary" type="submit"><i class="ri-filter-3-line" aria-hidden="true"></i> Aplicar filtros</button>
        </div>
    </form>
</section>
<section class="content-card programmed-service-results">
    <div class="card-heading">
        <div><h2>Servicios entregados</h2><p class="muted">{{ number_format($asignaciones->count()) }} servicio(s) con registros de beneficiarios. Cada fila resume un servicio dentro de un proyecto, indicador y actividad.</p></div>
        @can('exportar informes por servicios excel')
            <a id="service-report-export" class="button button-excel-export" href="{{ route('servicios-programados.export', $filters) }}"><i class="ri-file-excel-2-line" aria-hidden="true"></i> Exportar Excel</a>
            <form id="service-report-export-form" method="post" action="{{ route('servicios-programados.export') }}" hidden>
                @csrf
                @foreach($filters as $field => $value)
                    @foreach(is_array($value) ? $value : [$value] as $item)<input type="hidden" name="{{ $field }}{{ is_array($value) ? '[]' : '' }}" value="{{ $item }}">@endforeach
                @endforeach
                <input type="hidden" name="table_selection" value="1">
                <span id="service-report-export-selection"></span>
            </form>
        @endcan
    </div>
    <p class="programmed-service-note">Solo se muestran servicios seleccionados en registros con beneficiarios. Las atenciones cuentan filas de beneficiarios: no son personas únicas ni unidades entregadas. SIA no registra la cantidad de unidades entregadas por servicio. El estado indica si la asignación está activa, no si hubo entrega.</p>
    @if($asignaciones->isEmpty())
        <div class="empty-state"><p>No hay servicios entregados registrados que coincidan con los filtros.</p></div>
    @endif
        <p class="muted programmed-service-responsive-help">En pantallas pequeñas, pulse + junto al servicio para ver los demás datos y acceder a sus beneficiarios.</p>
        <div class="table-wrap programmed-service-table-wrap">
            <table id="service-report-table" class="programmed-services-table display responsive" style="width:100%">
                <thead><tr><th data-priority="1">Servicio</th><th data-priority="5">Proyecto</th><th>Sector</th><th>Indicador</th><th>Actividad</th><th data-priority="2">Atenciones con servicio</th><th data-priority="3">Registros con servicio</th><th>Primera atención</th><th data-priority="6">Última atención</th><th>Estado de la asignación</th><th data-priority="4">Acciones</th></tr></thead>
                <tbody>
                @foreach($asignaciones as $asignacion)
                    @php
                        $activity = $asignacion->actividadIndicador;
                        $indicator = $activity->indicadorProyecto;
                        $project = $indicator->proyecto;
                    @endphp
                    <tr data-service-assignment="{{ $asignacion->id }}">
                        <td><strong>{{ $asignacion->servicio->nombre }}</strong>@if($asignacion->servicio->descripcion)<small>{{ $asignacion->servicio->descripcion }}</small>@endif</td>
                        <td><strong>{{ $project->codigo }}</strong><small>{{ $project->nombre_alias ?: $project->descripcion }}</small>@if(!$project->estatus)<small class="text-warning">Proyecto inactivo</small>@endif</td>
                        <td>{{ $indicator->asignacionSector?->sector?->name ?? 'Sin sector asignado' }}</td>
                        <td><strong>{{ $indicator->indicador->codigo }}</strong><small>{{ $indicator->indicador->descripcion }}</small>@if(!$indicator->estatus)<small class="text-warning">Indicador inactivo</small>@endif</td>
                        <td><strong>{{ $activity->actividad->codigo }}</strong><small>{{ $activity->actividad->descripcion }}</small>@if(!$activity->estatus)<small class="text-warning">Actividad inactiva</small>@endif</td>
                        <td class="programmed-service-quantity" data-order="{{ $asignacion->beneficiaries_count }}">{{ number_format($asignacion->beneficiaries_count) }}<small>No son personas únicas ni unidades</small></td>
                        <td data-order="{{ $asignacion->reports_count }}">{{ number_format($asignacion->reports_count) }}<small>Registros, no beneficiarios</small></td>
                        <td data-order="{{ $asignacion->reports_min_report_date }}">{{ $asignacion->reports_min_report_date ? \Illuminate\Support\Carbon::parse($asignacion->reports_min_report_date)->format('d/m/Y') : 'Sin fecha' }}</td>
                        <td data-order="{{ $asignacion->reports_max_report_date }}">{{ $asignacion->reports_max_report_date ? \Illuminate\Support\Carbon::parse($asignacion->reports_max_report_date)->format('d/m/Y') : 'Sin fecha' }}</td>
                        <td><span class="status {{ $asignacion->estatus ? 'status-active' : 'status-inactive' }}">{{ $asignacion->estatus ? 'Activo' : 'Inactivo' }}</span></td>
                        <td><div class="programmed-service-row-actions">
                            @if(auth()->user()->isAdministrator())<a href="{{ route('actividad-indicador.servicios.index', $activity) }}">Ver configuración</a>@endif
                            @can('solo ver registros')<a href="{{ route('reports.index', ['servicio_actividad_id' => $asignacion->id, 'reported' => '', 'reporting_period' => $filters['reporting_period'] ?: '', 'from' => $filters['from'], 'to' => $filters['to']]) }}">Ver beneficiarios</a>@endcan
                        </div></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
</section>
@endsection
@push('scripts')
<script src="{{ asset('vendor/datatables/dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('js/service-report-table.js') }}?v={{ filemtime(public_path('js/service-report-table.js')) }}"></script>
@endpush
