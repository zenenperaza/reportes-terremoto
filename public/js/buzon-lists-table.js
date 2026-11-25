document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const element = document.getElementById('buzon-lists-table');
    if (!element || typeof DataTable === 'undefined') return;
    const filters = JSON.parse(element.dataset.filters || '{}');
    const total = document.getElementById('buzon-lists-total');
    const number = value => Number(value).toLocaleString('es-VE');
    new DataTable(element, {
        serverSide: true, processing: true, searchDelay: 450, pageLength: 20, lengthMenu: [20, 50, 100],
        order: [[0, 'desc']], orderMulti: false, autoWidth: false,
        responsive: {details: {type: 'inline', target: 0}},
        ajax: {url: element.dataset.url, data(data) {Object.assign(data, filters);}},
        columns: [
            {data: 'id', render(value, type) {return type === 'display' ? '#' + Number(value) : value;}},
            ...['created_at', 'project', 'creator'].map(field => ({data: field, render: DataTable.render.text()})),
            {data: 'entries_count', render: DataTable.render.number('.', ',', 0)},
            {data: 'actions', orderable: false, searchable: false},
        ],
        columnDefs: [{targets: 0, className: 'all', responsivePriority: 1},
            {targets: 2, responsivePriority: 2}, {targets: 5, responsivePriority: 3}],
        layout: {topStart: 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: 'paging'},
        language: {processing: 'Consultando listados…', search: 'Buscar:', searchPlaceholder: 'N.º, proyecto, fecha o usuario',
            lengthMenu: 'Mostrar _MENU_ listados', info: 'Mostrando _START_ a _END_ de _TOTAL_ listados',
            infoEmpty: 'Mostrando 0 listados', infoFiltered: '(filtrado de _MAX_ listados)',
            zeroRecords: 'No se encontraron listados coincidentes', emptyTable: 'No hay listados disponibles para los proyectos seleccionados.',
            paginate: {first: 'Primero', previous: 'Anterior', next: 'Siguiente', last: 'Último'}},
        drawCallback() {
            const info = this.api().page.info();
            total.textContent = 'Total de listados: ' + number(info.recordsDisplay)
                + (info.recordsDisplay !== info.recordsTotal ? ' (de ' + number(info.recordsTotal) + ' disponibles)' : '');
        },
    });
    document.querySelector('[data-buzon-lists-fallback]')?.setAttribute('hidden', '');
});
