@extends('layouts.app')
@section('title', 'Bitácora | SIA')

@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.dataTables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/audit-log.css') }}?v={{ @filemtime(public_path('css/audit-log.css')) ?: 1 }}">
@endpush

@section('content')
<section class="page-heading audit-heading">
    <div>
        <p class="eyebrow">Administración</p>
        <h1>Bitácora del sistema</h1>
        <p class="muted">Consulte la actividad realizada por los usuarios y el resultado de cada solicitud.</p>
    </div>
</section>

<section class="content-card audit-filter-card">
    <form id="audit-filters" class="audit-filters">
        <label>Usuario
            <select name="user_id" id="audit-user-filter">
                <option value="">Todos</option>
                @foreach($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }} — {{ $user->email }}</option>
                @endforeach
            </select>
        </label>
        <label>Método
            <select name="method" id="audit-method-filter">
                <option value="">Todos</option>
                @foreach(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method)
                    <option value="{{ $method }}">{{ $method }}</option>
                @endforeach
            </select>
        </label>
        <label>Resultado
            <select name="status" id="audit-status-filter">
                <option value="">Todos</option>
                <option value="success">Correcto</option>
                <option value="client_error">Error de solicitud</option>
                <option value="server_error">Error del servidor</option>
            </select>
        </label>
        <label>Desde<input type="date" name="from" id="audit-from-filter"></label>
        <label>Hasta<input type="date" name="to" id="audit-to-filter"></label>
        <div class="audit-filter-actions">
            <button type="button" class="button button-ghost" id="audit-clear-filters"><i class="ri-refresh-line"></i> Limpiar</button>
            <button type="submit" class="button button-primary"><i class="ri-filter-3-line"></i> Aplicar filtros</button>
        </div>
    </form>
</section>

<section class="content-card audit-table-card">
    <div class="table-wrap">
        <table id="audit-log-table" class="display audit-log-table" style="width:100%">
            <thead>
                <tr>
                    <th>Fecha y hora</th>
                    <th>Usuario</th>
                    <th>Acción</th>
                    <th>Método</th>
                    <th>Dirección</th>
                    <th>Resultado</th>
                    <th>IP</th>
                    <th>Duración</th>
                    <th>Detalles</th>
                </tr>
            </thead>
        </table>
    </div>
</section>

<div class="modal fade" id="audit-detail-modal" tabindex="-1" aria-labelledby="audit-detail-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5" id="audit-detail-title">Detalle de la acción</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body"><div id="audit-detail-content" class="audit-detail-content"></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/datatables/dataTables.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const tableElement = document.getElementById('audit-log-table');
    const filters = document.getElementById('audit-filters');
    const detailModalElement = document.getElementById('audit-detail-modal');
    if (!tableElement || typeof DataTable === 'undefined') return;

    const escapeHtml = (value) => String(value ?? '').replace(/[&<>'"]/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'
    })[character]);

    const table = new DataTable(tableElement, {
        processing: true,
        serverSide: true,
        searchDelay: 450,
        pageLength: 25,
        lengthMenu: [[25, 50, 100], [25, 50, 100]],
        order: [[0, 'desc']],
        ajax: {
            url: @json(route('audit-logs.data')),
            data: function (data) {
                data.user_id = document.getElementById('audit-user-filter').value;
                data.method = document.getElementById('audit-method-filter').value;
                data.status = document.getElementById('audit-status-filter').value;
                data.from = document.getElementById('audit-from-filter').value;
                data.to = document.getElementById('audit-to-filter').value;
            }
        },
        columns: [
            {data: 'created_at', name: 'created_at'},
            {data: 'user_name', name: 'user_name', render: function (data, type, row) {
                if (type !== 'display') return data;
                return '<strong>' + escapeHtml(data) + '</strong><small>' + escapeHtml(row.user_email) + '</small>';
            }},
            {data: 'action', name: 'action'},
            {data: 'method', name: 'method', render: function (data, type) {
                return type === 'display' ? '<span class="audit-method audit-method-' + escapeHtml(data.toLowerCase()) + '">' + escapeHtml(data) + '</span>' : data;
            }},
            {data: 'path', name: 'path', render: function (data, type, row) {
                if (type !== 'display') return data;
                return '<code>' + escapeHtml(data) + '</code><small>' + escapeHtml(row.route_name || 'Sin nombre de ruta') + '</small>';
            }},
            {data: 'status_code', name: 'status_code', render: function (data, type) {
                if (type !== 'display') return data;
                const tone = data >= 500 ? 'danger' : (data >= 400 ? 'warning' : 'success');
                return '<span class="audit-result audit-result-' + tone + '">' + escapeHtml(data) + '</span>';
            }},
            {data: 'ip_address', name: 'ip_address'},
            {data: 'duration_ms', name: 'duration_ms', render: data => escapeHtml(data) + ' ms'},
            {data: 'id', orderable: false, searchable: false, render: function (data) {
                return '<button type="button" class="btn btn-sm btn-soft-primary audit-detail-button" data-audit-id="' + escapeHtml(data) + '"><i class="ri-eye-line"></i> Ver</button>';
            }}
        ],
        columnDefs: [{targets: [3, 5, 7, 8], className: 'dt-body-center'}],
        language: {
            processing: 'Consultando la bitácora…',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ acciones',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ acciones',
            infoEmpty: 'No hay acciones registradas',
            infoFiltered: '(filtrado de _MAX_ acciones)',
            zeroRecords: 'No se encontraron acciones coincidentes',
            emptyTable: 'La bitácora todavía está vacía',
            paginate: {first: 'Primero', previous: 'Anterior', next: 'Siguiente', last: 'Último'}
        }
    });

    filters.addEventListener('submit', function (event) {
        event.preventDefault();
        table.ajax.reload();
    });

    document.getElementById('audit-clear-filters').addEventListener('click', function () {
        filters.reset();
        table.search('').draw();
    });

    tableElement.addEventListener('click', async function (event) {
        const button = event.target.closest('.audit-detail-button');
        if (!button) return;

        const content = document.getElementById('audit-detail-content');
        content.innerHTML = '<p class="muted">Cargando detalles…</p>';
        bootstrap.Modal.getOrCreateInstance(detailModalElement).show();

        try {
            const url = @json(route('audit-logs.show', ['auditLog' => '__AUDIT_ID__'])).replace('__AUDIT_ID__', button.dataset.auditId);
            const response = await fetch(url, {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('No fue posible consultar el detalle.');
            const detail = await response.json();
            const fields = [
                ['Fecha', detail.fecha], ['Usuario', detail.usuario], ['Correo', detail.correo],
                ['Acción', detail.accion], ['Método', detail.metodo], ['Ruta', detail.ruta],
                ['Dirección', detail.direccion], ['Resultado HTTP', detail.resultado],
                ['Duración', detail.duracion_ms + ' ms'], ['IP', detail.ip], ['Navegador', detail.navegador]
            ];
            content.innerHTML = '<dl>' + fields.map(field => '<div><dt>' + escapeHtml(field[0]) + '</dt><dd>' + escapeHtml(field[1] || 'Sin información') + '</dd></div>').join('') + '</dl>' +
                '<h3>Datos enviados</h3><pre>' + escapeHtml(detail.datos ? JSON.stringify(detail.datos, null, 2) : 'No se enviaron datos.') + '</pre>';
        } catch (error) {
            content.innerHTML = '<div class="alert alert-danger">' + escapeHtml(error.message) + '</div>';
        }
    });
});
</script>
@endpush
