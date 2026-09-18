(function () {
    'use strict';

    const nativeButtons = {copy: 'copyHtml5', csv: 'csvHtml5', excel: 'excelHtml5', pdf: 'pdfHtml5'};

    function plainText(value) {
        if (typeof value === 'number') return value;
        const container = document.createElement('div');
        container.innerHTML = String(value ?? '').replace(/<br\s*\/?\s*>/gi, ' | ');
        return container.textContent.replace(/\s+/g, ' ').trim();
    }

    function safeCell(value) {
        const text = plainText(value);
        // Prevent user-entered text from becoming a spreadsheet formula.
        return typeof text === 'string' && /^[=+\-@\t\r]/.test(text) ? "'" + text : text;
    }

    function printRows(popup, title, headers, rows) {
        const doc = popup.document;
        doc.title = title;
        const style = doc.createElement('style');
        style.textContent = '@page{size:A3 landscape;margin:12mm}body{font:11px Arial,sans-serif;color:#111}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccc;padding:5px;text-align:left;vertical-align:top;overflow-wrap:anywhere}thead{display:table-header-group}tr{break-inside:avoid}button{padding:10px;margin:10px 0}@media print{button{display:none}}';
        doc.head.appendChild(style);
        doc.body.replaceChildren();
        const heading = doc.createElement('h1');
        heading.textContent = title;
        doc.body.appendChild(heading);
        const button = doc.createElement('button');
        button.textContent = 'Imprimir';
        button.onclick = () => popup.print();
        doc.body.appendChild(button);
        const table = doc.createElement('table');
        const header = table.createTHead().insertRow();
        headers.forEach(text => {
            const cell = doc.createElement('th');
            cell.textContent = plainText(text);
            header.appendChild(cell);
        });
        const body = table.createTBody();
        rows.forEach(values => {
            const row = body.insertRow();
            values.forEach(value => { row.insertCell().textContent = value; });
        });
        doc.body.appendChild(table);
        popup.focus();
        popup.setTimeout(() => { if (!popup.closed) popup.print(); }, 250);
    }

    window.reportExportAction = function (format, filters) {
        return async function (event, dt, node, config, done) {
            const status = document.getElementById('report-export-status');
            // Open synchronously during the click so popup blockers do not discard printing.
            const popup = format === 'print' ? window.open('', '_blank') : null;
            if (popup) {
                popup.opener = null;
                popup.document.body.textContent = 'Preparando todos los registros…';
            }
            dt.buttons().disable();
            status.textContent = 'Preparando todos los registros de la búsqueda…';
            status.hidden = false;
            try {
                if (format === 'print' && !popup) throw new Error('Permita las ventanas emergentes para imprimir.');
                const url = new URL(dt.ajax.url(), window.location.href);
                Object.entries(filters).forEach(([key, value]) => url.searchParams.set(key, value ?? ''));
                url.searchParams.set('draw', '0');
                url.searchParams.set('export_type', format);
                // Include text just typed, even if the debounced table request has not run yet.
                const searchInput = dt.table().container().querySelector('.dt-search input');
                url.searchParams.set('search[value]', searchInput ? searchInput.value : dt.search());
                dt.order().forEach(([column, dir], index) => {
                    url.searchParams.set(`order[${index}][column]`, column);
                    url.searchParams.set(`order[${index}][dir]`, dir);
                });
                const response = await fetch(url, {headers: {'Accept': 'application/json'}, credentials: 'same-origin', cache: 'no-store'});
                if (!response.ok) throw new Error('No se pudo preparar la exportación. Revise su sesión y sus permisos.');
                const payload = await response.json();
                if (!Array.isArray(payload.data)) throw new Error('La respuesta de exportación no es válida.');
                const indexes = dt.columns(config.exportOptions.columns).indexes().toArray();
                const fields = indexes.map(index => dt.column(index).dataSrc());
                if (payload.data.some(row => fields.some(field => !Object.hasOwn(row, field)))) {
                    throw new Error('Los permisos o las columnas cambiaron. Recargue la página antes de exportar.');
                }
                const body = payload.data.map(row => fields.map(field => format === 'print' || format === 'pdf' ? plainText(row[field]) : safeCell(row[field])));
                const exportConfig = {...config, exportOptions: {...config.exportOptions, customizeData(data) { data.body = body; }}};
                if (format === 'print') {
                    if (popup.closed) throw new Error('Se cerró la ventana de impresión.');
                    const data = dt.buttons.exportData(exportConfig.exportOptions);
                    printRows(popup, config.title, data.header, body);
                } else {
                    await new Promise((resolve, reject) => {
                        try { DataTable.ext.buttons[nativeButtons[format]].action.call(this, event, dt, node, exportConfig, resolve); }
                        catch (error) { reject(error); }
                    });
                }
                status.textContent = `Exportación preparada: ${body.length.toLocaleString('es-VE')} registros.`;
            } catch (error) {
                if (popup && !popup.closed) popup.close();
                status.textContent = error.message || 'No se pudo completar la exportación. Inténtelo nuevamente.';
            } finally {
                dt.buttons().enable();
                if (typeof done === 'function') done();
            }
        };
    };
})();
