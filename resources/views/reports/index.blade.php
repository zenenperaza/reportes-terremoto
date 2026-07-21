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
            <select name="reported"><option value="">Todos</option><option value="1" @selected(($filters['reported'] ?? '') === '1')>Sí</option><option value="0" @selected(($filters['reported'] ?? '') === '0')>No</option></select>
        </label>
        <button class="button button-secondary" type="submit">Aplicar filtros</button>
    </form>
</section>

<section class="content-card">
    @if ($reports->isEmpty())
        <div class="empty-state"><p>No hay registros que coincidan con los filtros.</p></div>
    @else
        <div class="table-wrap"><table id="activity-records-table" class="activity-records-table">
            <thead><tr><th>Fecha atencion</th>@if($isCoordinator)<th>Registrado por</th>@endif<th>Ubicación</th><th>Actividad</th><th>Beneficiarios</th><th>Reportado</th><th></th></tr></thead>
            <tbody>@foreach($reports as $report)
                <tr>
                    <td data-order="{{ $report->report_date->format('Y-m-d') }}">{{ $report->report_date->format('d/m/Y') }}</td>
                    @if($isCoordinator)<td>{{ $report->reporter_first_name }} {{ $report->reporter_last_name }}<br><small>{{ $report->organization }}</small></td>@endif
                    <td>{{ $report->state->name }}<br><small>{{ $report->municipality->name }}, {{ $report->parish->name }}</small></td>
                    <td>{{ $report->sector->name }}<br><small>{{ \Illuminate\Support\Str::limit($report->activity->title, 72) }}</small></td>
                    <td data-order="{{ $report->total_beneficiaries }}">{{ number_format($report->total_beneficiaries) }}</td>
                    @php($isReported = $report->beneficiaries_count > 0 && $report->unreported_beneficiaries_count === 0)
                    <td><span class="status status-{{ $isReported ? 'reviewed' : 'submitted' }}">{{ $isReported ? 'Sí' : 'No' }}</span>@if(! $isReported && $report->beneficiaries_count > $report->unreported_beneficiaries_count)<br><small>{{ $report->beneficiaries_count - $report->unreported_beneficiaries_count }} de {{ $report->beneficiaries_count }} beneficiarios reportados</small>@endif</td>
                    <td><a href="{{ route('reports.show', $report) }}">Ver</a></td>
                </tr>
            @endforeach</tbody>
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

    if (activityRecordsTable && typeof DataTable !== 'undefined') {
        new DataTable(activityRecordsTable, {
            layout: {
                topStart: ['pageLength', {
                    buttons: [
                        {extend: 'copyHtml5', text: 'Copiar'},
                        {extend: 'csvHtml5', text: 'CSV', title: 'Registros de actividades'},
                        {extend: 'excelHtml5', text: 'Excel', title: 'Registros de actividades'},
                        {extend: 'pdfHtml5', text: 'PDF', title: 'Registros de actividades', orientation: 'landscape', pageSize: 'A4'},
                        {extend: 'print', text: 'Imprimir', title: 'Registros de actividades'},
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
                emptyTable: 'No hay registros que coincidan con los filtros.',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ registros',
                infoEmpty: 'Mostrando 0 a 0 de 0 registros',
                infoFiltered: '(filtrado de _MAX_ registros)',
                lengthMenu: 'Mostrar _MENU_ registros',
                search: 'Buscar:',
                zeroRecords: 'No se encontraron registros coincidentes',
                paginate: {first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior'},
            },
        });
    }
</script>
@endsection
