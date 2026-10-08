document.addEventListener('DOMContentLoaded', function () {
    const element = document.getElementById('service-report-table');
    if (!element || typeof DataTable === 'undefined') return;
    const deliveryRows = element.dataset?.deliveryRows === '1';
    const unit = deliveryRows ? 'filas' : 'servicios';

    const table = new DataTable(element, {
        responsive: {details: {type: 'inline', target: 0}},
        autoWidth: true,
        pageLength: 15,
        lengthMenu: [15, 25, 50, 100],
        order: [[0, 'asc']],
        columnDefs: [
            {targets: 10, orderable: false, searchable: false},
            {targets: 0, className: 'dtr-control', responsivePriority: 1}
        ],
        language: {
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ ' + unit,
            info: 'Mostrando _START_ a _END_ de _TOTAL_ ' + unit,
            infoEmpty: 'No hay servicios entregados registrados',
            infoFiltered: '(filtrado de _MAX_ ' + unit + ')',
            zeroRecords: 'No se encontraron servicios coincidentes',
            emptyTable: 'No hay servicios entregados registrados que coincidan con los filtros',
            paginate: {first: 'Primero', previous: 'Anterior', next: 'Siguiente', last: 'Último'}
        }
    });

    const exportButton = document.getElementById('service-report-export');
    const exportForm = document.getElementById('service-report-export-form');
    const selection = document.getElementById('service-report-export-selection');
    if (!exportButton || !exportForm || !selection) return;

    exportButton.addEventListener('click', function (event) {
        event.preventDefault();
        selection.replaceChildren();
        // Applied search, across ALL pages. Server re-applies the report filters.
        const ids = table.rows({search: 'applied', page: 'all'}).nodes().toArray().map(row => deliveryRows ? row.dataset.deliveryKey : row.dataset.serviceAssignment);
        // One field avoids PHP's max_input_vars truncating exports beyond 1000 rows.
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = deliveryRows ? 'row_keys_json' : 'assignment_ids_json';
        input.value = JSON.stringify(ids);
        selection.appendChild(input);
        exportForm.submit();
    });
});
