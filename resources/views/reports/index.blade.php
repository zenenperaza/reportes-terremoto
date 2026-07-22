@extends('layouts.app')

@section('title', 'Registros | Respuesta ASONACOP')

@push('styles')
    <link rel="stylesheet" href="/vendor/datatables/dataTables.dataTables.min.css">
    <link rel="stylesheet" href="/vendor/datatables/buttons.dataTables.min.css">
    <link rel="stylesheet" href="/css/beneficiary-datatable.css">
@endpush

@section('content')
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
            <select name="reported"><option value="">Todos</option><option value="1" @selected(($filters['reported'] ?? '') === '1')>Sí</option><option value="0" @selected(($filters['reported'] ?? '') === '0')>No</option></select>
        </label>
        <button class="button button-secondary" type="submit">Aplicar filtros</button>
    </form>
</section>

<section class="content-card">
    @if (($isCoordinator ? $beneficiaries : $reports)->isEmpty())
        <div class="empty-state"><p>No hay registros que coincidan con los filtros.</p></div>
    @else
        <div class="table-wrap"><table id="activity-records-table" class="activity-records-table">
            <thead>
                @if ($isCoordinator)
                    <tr><th>Fecha atención</th><th>Registrado por</th><th>Beneficiario</th><th>Ubicación</th><th>Actividad</th><th>Recurrente</th><th>Reportado</th><th></th></tr>
                @else
                    <tr><th>Fecha atención</th><th>Ubicación</th><th>Actividad</th><th>Beneficiarios</th><th>Reportado</th><th></th></tr>
                @endif
            </thead>
            <tbody>
            @if ($isCoordinator)
                @foreach ($beneficiaries as $beneficiary)
                    @php($report = $beneficiary->report)
                    <tr>
                        <td data-order="{{ $report->report_date->format('Y-m-d') }}">{{ $report->report_date->format('d/m/Y') }}</td>
                        <td>{{ $report->reporter_first_name }} {{ $report->reporter_last_name }}<br><small>{{ $report->organization }}</small></td>
                        <td>
                            {{ $beneficiary->full_name ?: 'Sin nombre registrado' }}
                            <br><small>{{ $beneficiary->age }} años · {{ $beneficiary->sex }}@if($beneficiary->national_id) · Cédula: {{ $beneficiary->national_id }}@endif</small>
                            @if($beneficiary->phone)<br><small>Tel.: {{ $beneficiary->phone }}</small>@endif
                        </td>
                        <td>{{ $report->state->name }}<br><small>{{ $report->municipality->name }}, {{ $report->parish->name }}</small><br><small>{{ $report->place_name }}</small></td>
                        <td>{{ $report->sector->name }}<br><small>{{ \Illuminate\Support\Str::limit($report->activity->title, 72) }}</small></td>
                        <td><span class="status status-{{ $beneficiary->is_recurrent ? 'submitted' : 'reviewed' }}">{{ $beneficiary->is_recurrent ? 'Sí' : 'No' }}</span></td>
                        <td><span class="status status-{{ $beneficiary->reported_at ? 'reviewed' : 'submitted' }}">{{ $beneficiary->reported_at ? 'Sí' : 'No' }}</span>@if($beneficiary->reported_at)<br><small>{{ $beneficiary->reported_at->format('d/m/Y') }}</small>@endif</td>
                        <td><a href="{{ route('reports.show', $report) }}">Ver</a></td>
                    </tr>
                @endforeach
            @else
            @foreach($reports as $report)
                <tr>
                    <td data-order="{{ $report->report_date->format('Y-m-d') }}">{{ $report->report_date->format('d/m/Y') }}</td>
                    <td>{{ $report->state->name }}<br><small>{{ $report->municipality->name }}, {{ $report->parish->name }}</small></td>
                    <td>{{ $report->sector->name }}<br><small>{{ \Illuminate\Support\Str::limit($report->activity->title, 72) }}</small></td>
                    <td data-order="{{ $report->total_beneficiaries }}">{{ number_format($report->total_beneficiaries) }}</td>
                    @php($isReported = $report->beneficiaries_count > 0 && $report->unreported_beneficiaries_count === 0)
                    <td><span class="status status-{{ $isReported ? 'reviewed' : 'submitted' }}">{{ $isReported ? 'Sí' : 'No' }}</span>@if(! $isReported && $report->beneficiaries_count > $report->unreported_beneficiaries_count)<br><small>{{ $report->beneficiaries_count - $report->unreported_beneficiaries_count }} de {{ $report->beneficiaries_count }} beneficiarios reportados</small>@endif</td>
                    <td><a href="{{ route('reports.show', $report) }}">Ver</a></td>
                </tr>
            @endforeach
            @endif
            </tbody>
        </table></div>
    @endif
</section>

<script src="/vendor/datatables/jquery-3.7.1.min.js"></script>
<script src="/vendor/datatables/dataTables.min.js"></script>
<script src="/vendor/datatables/dataTables.buttons.min.js"></script>
<script src="/vendor/datatables/jszip.min.js"></script>
<script src="/vendor/datatables/pdfmake.min.js"></script>
<script src="/vendor/datatables/vfs_fonts.js"></script>
<script src="/vendor/datatables/buttons.html5.min.js"></script>
<script src="/vendor/datatables/buttons.print.min.js"></script>
<script>
    const activityRecordsTable = document.getElementById('activity-records-table');
    const activityRowsLabel = @json($isCoordinator ? 'beneficiarios' : 'registros');
    const activityExportTitle = @json($isCoordinator ? 'Beneficiarios individuales - Consolidado de respuesta' : 'Registros de actividades');

    if (activityRecordsTable && typeof DataTable !== 'undefined') {
        new DataTable(activityRecordsTable, {
            layout: {
                topStart: ['pageLength', {
                    buttons: [
                        {extend: 'copyHtml5', text: 'Copiar'},
                        {extend: 'csvHtml5', text: 'CSV', title: activityExportTitle},
                        {extend: 'excelHtml5', text: 'Excel', title: activityExportTitle},
                        {extend: 'pdfHtml5', text: 'PDF', title: activityExportTitle, orientation: 'landscape', pageSize: 'A4'},
                        {extend: 'print', text: 'Imprimir', title: activityExportTitle},
                    ],
                }],
                topEnd: 'search',
                bottomStart: 'info',
                bottomEnd: 'paging',
            },
            pageLength: 15,
            lengthMenu: [[15, 25, 50, -1], [15, 25, 50, 'Todos']],
            order: [],
            columnDefs: [{targets: -1, orderable: false, searchable: false}],
            language: {
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
