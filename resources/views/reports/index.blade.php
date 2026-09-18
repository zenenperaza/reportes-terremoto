@extends('layouts.app')

@section('title', 'Registros | SIA')

@push('styles')
    <link rel="stylesheet" href="/vendor/datatables/dataTables.dataTables.min.css">
    <link rel="stylesheet" href="/vendor/datatables/buttons.dataTables.min.css">
    <link rel="stylesheet" href="/vendor/datatables/responsive.dataTables.min.css">
    <link rel="stylesheet" href="/css/beneficiary-datatable.css">
    <link rel="stylesheet" href="{{ asset('css/report-datatable.css') }}?v={{ filemtime(public_path('css/report-datatable.css')) }}">
@endpush

@section('content')
@php($canViewReportDetail = auth()->user()->can('ver detalle de registros'))
@php($canExportExcel = auth()->user()->can('exportar registros excel'))
@php($canExportPdf = auth()->user()->can('exportar registros pdf'))
<section class="page-heading">
    <div>
        <p class="eyebrow">{{ $isCoordinator ? 'Consolidado de respuesta' : 'Historial personal' }}</p>
        <h1>Registros de actividades</h1>
        <p class="muted">
            @if ($isCoordinator)
                Cada fila corresponde a un beneficiario individual y conserva los datos de su actividad.
            @else
                Filtre por ubicación, fecha o estado para localizar rápidamente un registro.
            @endif
        </p>
    </div>
    <div class="heading-actions">
        @if (auth()->user()->isAdministrator())
            <a class="button button-secondary" href="{{ route('reports.export', request()->query()) }}">Exportar CSV</a>
        @endif
        @can('registrar actividad')<a class="button button-primary" href="{{ route('reports.create') }}">+ Nuevo registro</a>@endcan
    </div>
</section>

<section class="content-card filter-card">
    <form method="get" class="filters">
        <label>Estado
            <select name="state_id"><option value="">Todos</option>@foreach($states as $state)<option value="{{ $state->id }}" @selected(($filters['state_id'] ?? '') == $state->id)>{{ $state->name }}</option>@endforeach</select>
        </label>
        <label>Desde<input type="date" name="from" value="{{ $filters['from'] ?? '' }}"></label>
        <label>Hasta<input type="date" name="to" value="{{ $filters['to'] ?? '' }}"></label>
        <label>Reportados
            <select name="reported"><option value="">Todos</option><option value="1" @selected(($filters['reported'] ?? '') === '1')>Sí</option><option value="0" @selected(($filters['reported'] ?? '') === '0')>No</option></select>
        </label>
        @if ($isCoordinator)
            <label>Registrado por
                <select name="user_id">
                    <option value="">Todos los usuarios</option>
                    @foreach ($registeringUsers as $registeringUser)
                        <option value="{{ $registeringUser->id }}" @selected((string) ($filters['user_id'] ?? '') === (string) $registeringUser->id)>{{ $registeringUser->name }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <button class="button button-secondary" type="submit">Aplicar filtros</button>
    </form>
</section>

<section class="content-card report-table-card">
    @if (! $isCoordinator && $reports->isEmpty())
        <div class="empty-state"><p>No hay registros que coincidan con los filtros.</p></div>
    @else
        @if($isCoordinator)
            <p id="report-export-status" class="muted report-table-help" role="status" aria-live="polite" hidden></p>
            <p id="report-table-error" class="alert alert-error" role="alert" hidden>No se pudieron cargar los registros. Revise su conexión o recargue la página para volver a intentarlo.</p>
        @endif
        <div class="table-wrap report-table-wrap"><table id="activity-records-table" class="activity-records-table display responsive" style="width:100%">
            <thead>
                @if ($isCoordinator)
                    <tr><th data-priority="1">Fecha atención</th><th>Registrado por</th><th>Fecha registro</th>@if($canViewPersonalData)<th>Nombres</th><th>Cédula</th><th>Teléfono</th>@endif<th>Edad / sexo</th><th>Ubicación</th><th>Proyecto</th><th data-priority="3">Indicadores</th><th data-priority="4">Actividades</th><th data-priority="5">Servicios</th><th data-priority="2">N.º de servicios</th><th>Recurrente</th><th>Reportado</th>@if($canViewReportDetail)<th class="no-export" data-priority="6">Acciones</th>@endif</tr>
                @else
                    <tr><th data-priority="1">Fecha atención</th><th>Ubicación</th><th>Proyecto</th><th data-priority="3">Indicadores</th><th data-priority="4">Actividades</th><th data-priority="5">Servicios</th><th data-priority="2">N.º de servicios</th><th>Beneficiarios</th><th>Reportado</th>@if($canViewReportDetail)<th class="no-export" data-priority="6">Acciones</th>@endif</tr>
                @endif
            </thead>
            <tbody>
            @if (! $isCoordinator)
            @foreach($reports as $report)
                <tr>
                    <td data-order="{{ $report->report_date->format('Y-m-d') }}">{{ $report->report_date->format('d/m/Y') }}</td>
                    <td>{{ $report->state->name }}<br><small>{{ $report->municipality->name }}, {{ $report->parish->name }}</small></td>
                    @include('reports._classification-columns', ['report' => $report])
                    <td data-order="{{ $report->total_beneficiaries }}">{{ number_format($report->total_beneficiaries) }}</td>
                    @php($isReported = $report->beneficiaries_count > 0 && $report->unreported_beneficiaries_count === 0)
                    <td><span class="status status-{{ $isReported ? 'reviewed' : 'submitted' }}">{{ $isReported ? 'Sí' : 'No' }}</span>@if(! $isReported && $report->beneficiaries_count > $report->unreported_beneficiaries_count)<br><small>{{ $report->beneficiaries_count - $report->unreported_beneficiaries_count }} de {{ $report->beneficiaries_count }} beneficiarios reportados</small>@endif</td>
                    @if($canViewReportDetail)<td class="report-actions"><a href="{{ route('reports.show', $report) }}">Ver</a></td>@endif
                </tr>
            @endforeach
            @endif
            </tbody>
        </table></div>
    @endif
</section>

<script src="/vendor/datatables/jquery-3.7.1.min.js"></script>
<script src="/vendor/datatables/dataTables.min.js"></script>
<script src="/vendor/datatables/dataTables.responsive.min.js"></script>
<script src="/vendor/datatables/dataTables.buttons.min.js"></script>
<script src="/vendor/datatables/jszip.min.js"></script>
<script src="/vendor/datatables/pdfmake.min.js"></script>
<script src="/vendor/datatables/vfs_fonts.js"></script>
<script src="/vendor/datatables/buttons.html5.min.js"></script>
<script src="/vendor/datatables/buttons.print.min.js"></script>
@if($isCoordinator)<script src="{{ asset('js/report-export.js') }}?v={{ filemtime(public_path('js/report-export.js')) }}"></script>@endif
<script>
    const activityRecordsTable = document.getElementById('activity-records-table');
    const activityRowsLabel = @json($isCoordinator ? 'beneficiarios' : 'registros');
    const activityExportTitle = @json($isCoordinator ? 'Beneficiarios individuales - Consolidado de respuesta' : 'Registros de actividades');
    const fullReportExport = format => (@if($isCoordinator){async: 0, action: window.reportExportAction(format, @json($filters))}@else{}@endif);
    // Incluye columnas replegadas por Responsive y excluye enlaces de acciones.
    const activityExportOptions = {
        columns: ':not(.no-export)',
        format: {
            body: function(data, row, column, node) {
                const content = node.cloneNode(true);
                content.querySelectorAll('br').forEach(br => br.replaceWith(document.createTextNode(' | ')));
                return content.textContent.replace(/\s+/g, ' ').trim();
            },
        },
    };

    if (activityRecordsTable && typeof DataTable !== 'undefined') {
        @if($isCoordinator)
        $(activityRecordsTable).on('xhr.dt', function(event, settings, json) {
            document.getElementById('report-table-error').hidden = !!json;
            if (!json) return true;
        });
        @endif
        new DataTable(activityRecordsTable, {
            responsive: true,
            autoWidth: true,
            @if($isCoordinator)
            processing: true,
            serverSide: true,
            searchDelay: 400,
            ajax: {
                url: @json(route('reports.index')),
                data: function(data) { Object.assign(data, @json($filters)); },
            },
            columns: @json($serverColumns),
            @endif
            layout: {
                topStart: ['pageLength', {
                    buttons: [
                        {extend: 'copyHtml5', text: 'Copiar', exportOptions: activityExportOptions, ...fullReportExport('copy')},
                        {extend: 'csvHtml5', text: 'CSV', title: activityExportTitle, exportOptions: activityExportOptions, ...fullReportExport('csv')},
                        @if($canExportExcel){extend: 'excelHtml5', text: 'Excel', title: activityExportTitle, exportOptions: activityExportOptions, ...fullReportExport('excel')},@endif
                        @if($canExportPdf){extend: 'pdfHtml5', text: 'PDF', title: activityExportTitle, orientation: 'landscape', pageSize: 'A3', exportOptions: activityExportOptions, ...fullReportExport('pdf')},@endif
                        {extend: 'print', text: 'Imprimir', title: activityExportTitle, exportOptions: activityExportOptions, ...fullReportExport('print')},
                    ],
                }],
                topEnd: 'search',
                bottomStart: 'info',
                bottomEnd: 'paging',
            },
            pageLength: 15,
            @if($isCoordinator)
            lengthMenu: [[15, 25, 50, 100], [15, 25, 50, 100]],
            @else
            lengthMenu: [[15, 25, 50, -1], [15, 25, 50, 'Todos']],
            @endif
            order: [],
            columnDefs: [@if($canViewReportDetail){targets: -1, orderable: false, searchable: false}@endif],
            language: {
                processing: 'Cargando registros…',
                loadingRecords: 'Cargando registros…',
                emptyTable: 'No hay ' + activityRowsLabel + ' que coincidan con los filtros.',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ ' + activityRowsLabel,
                infoEmpty: 'Mostrando 0 a 0 de 0 ' + activityRowsLabel,
                infoFiltered: '(filtrado de _MAX_ ' + activityRowsLabel + ')',
                lengthMenu: 'Mostrar _MENU_ ' + activityRowsLabel,
                search: 'Buscar:',
                zeroRecords: 'No se encontraron registros coincidentes',
                paginate: {first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior'},
            },
        });
    }
</script>
@endsection
