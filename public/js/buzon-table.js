document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const element = document.getElementById('buzon-table');
    if (!element || typeof DataTable === 'undefined') return;
    const filters = JSON.parse(element.dataset.filters || '{}');
    const fields = ['opinion_date', 'project', 'place_name', 'opinion', 'feedback_type', 'full_name', 'user_name', 'origin'];
    const status = document.getElementById('buzon-export-status');
    const safeCell = value => {
        const text = String(value ?? '');
        return /^(?:\s*[=+\-@]|[\t\r\n])/.test(text) ? "'" + text : text;
    };
    const exportAction = format => async function (event, table, node, config, done) {
        table.buttons().disable();
        status.hidden = false;
        status.textContent = 'Preparando todos los resultados de la búsqueda…';
        try {
            const url = new URL(element.dataset.url, window.location.href);
            Object.entries(filters).forEach(([key, value]) => {
                if (Array.isArray(value)) value.forEach(item => url.searchParams.append(key + '[]', item));
                else url.searchParams.set(key, value ?? '');
            });
            url.searchParams.set('draw', '0');
            url.searchParams.set('export_type', format);
            const input = table.table().container().querySelector('.dt-search input');
            url.searchParams.set('search[value]', input ? input.value : table.search());
            const order = table.order()[0] || [0, 'desc'];
            url.searchParams.set('order[0][column]', order[0]);
            url.searchParams.set('order[0][dir]', order[1]);
            const response = await fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
            if (!response.ok) throw new Error('No se pudo exportar. Revise su sesión, sus permisos y los filtros.');
            const payload = await response.json();
            if (!Array.isArray(payload.data) || payload.data.some(row => fields.some(field => !Object.hasOwn(row, field)))) {
                throw new Error('La respuesta de exportación no es válida. Recargue el Buzón.');
            }
            const body = payload.data.map(row => fields.map(field => format === 'excel' ? safeCell(row[field]) : String(row[field] ?? '')));
            const exportConfig = {...config, exportOptions: {...config.exportOptions, customizeData(data) {data.body = body;}}};
            await new Promise((resolve, reject) => {
                try { DataTable.ext.buttons[format + 'Html5'].action.call(this, event, table, node, exportConfig, resolve); }
                catch (error) { reject(error); }
            });
            status.textContent = `Exportación preparada: ${body.length} opiniones.`;
            window.SiaDesktopNotifications?.notify('exported');
        } catch (error) {
            status.textContent = error.message || 'No se pudo completar la exportación.';
            window.SiaDesktopNotifications?.notify('error');
        } finally {
            table.buttons().enable();
            if (typeof done === 'function') done();
        }
    };
    const exportOptions = {columns: fields.map((_, index) => index), stripHtml: false, decodeEntities: false, trim: false};
    const buttons = element.dataset.canExport === '1' ? [
        {extend: 'excelHtml5', text: 'Exportar Excel', className: 'buzon-export-excel', title: 'Buzón ASONACOP', filename: 'buzon-opiniones',
            exportOptions, async: 100, action: exportAction('excel')},
        {extend: 'pdfHtml5', text: 'Exportar PDF', className: 'buzon-export-pdf', title: 'Buzón ASONACOP', filename: 'buzon-opiniones',
            orientation: 'landscape', pageSize: 'A3', exportOptions, async: 100, action: exportAction('pdf'),
            customize(doc) {doc.defaultStyle.fontSize = 8; doc.styles.tableHeader.fontSize = 9; doc.pageMargins = [20, 25, 20, 25];}},
    ] : [];

    const table = new DataTable(element, {
        serverSide: true, processing: true, searchDelay: 450, pageLength: 20, lengthMenu: [20, 50, 100], order: [[0, 'desc']], orderMulti: false,
        responsive: {details: {type: 'inline', target: 0}}, autoWidth: false,
        ajax: {url: element.dataset.url, data(data) {Object.assign(data, filters);}},
        columns: [...fields.map(field => ({data: field, render: DataTable.render.text()})), {data: 'actions', orderable: false, searchable: false}],
        columnDefs: [{targets: 0, className: 'all', responsivePriority: 1}, {targets: 1, responsivePriority: 2},
            {targets: 8, responsivePriority: 3}, {targets: 6, orderable: false}],
        layout: {topStart: buttons.length ? ['pageLength', {buttons}] : 'pageLength', topEnd: 'search', bottomStart: 'info', bottomEnd: 'paging'},
        language: {processing: 'Consultando opiniones…', search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ opiniones',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ opiniones', infoEmpty: 'No hay opiniones registradas',
            infoFiltered: '(filtrado de _MAX_ opiniones)', zeroRecords: 'No se encontraron opiniones coincidentes',
            emptyTable: 'No hay formularios del Buzón que coincidan con los filtros.',
            paginate: {first: 'Primero', previous: 'Anterior', next: 'Siguiente', last: 'Último'}},
    });
    document.querySelector('[data-buzon-fallback-pagination]')?.setAttribute('hidden', '');
    // Draws replace the forms; confirm through delegation so paging never bypasses it.
    element.closest('.content-card').addEventListener('submit', function (event) {
        const form = event.target.closest('form[data-buzon-delete]');
        if (!form || event.defaultPrevented) return;
        if (form.dataset.deleting === '1' || !window.confirm('¿Eliminar este formulario del Buzón? Esta acción es permanente y no se puede deshacer.')) {
            event.preventDefault();
            return;
        }
        form.dataset.deleting = '1';
        form.querySelector('button[type="submit"]').disabled = true;
    });
});
