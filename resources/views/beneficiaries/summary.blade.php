@extends('layouts.app')

@section('title', 'Informe de beneficiarios | SIA')

@push('styles')
    <link rel="stylesheet" href="/vendor/datatables/dataTables.dataTables.min.css">
    <link rel="stylesheet" href="/vendor/datatables/buttons.dataTables.min.css">
    <link rel="stylesheet" href="/css/beneficiary-datatable.css">
@endpush

@section('content')
<section class="page-heading compact-heading">
    <div>
        <p class="eyebrow">{{ $isConsolidated ? 'Consolidado de respuesta' : 'Mis registros' }}</p>
        <h1>Informe de beneficiarios</h1>
        <p class="muted">Seleccione los filtros para obtener el total de personas alcanzadas y su desagregación.</p>
    </div>
</section>

<section class="content-card beneficiary-reported-card" aria-labelledby="summary-reported-title">
    <h2 id="summary-reported-title">Estado de reporte</h2>
    <form method="get" action="{{ route('beneficiaries.summary') }}" id="beneficiary-reported-filter" class="beneficiary-reported-filter">
        <label for="summary_reported">Reportado
            <select name="reported" id="summary_reported" aria-describedby="summary-reported-help">
                <option value="0" @selected(($filters['reported'] ?? '') === '0')>No reportados</option>
                <option value="1" @selected(($filters['reported'] ?? '') === '1')>Sí reportados</option>
                <option value="" @selected(($filters['reported'] ?? '') === '')>Todos</option>
            </select>
        </label>
        <button class="button button-primary" type="submit">Aplicar estado</button>
        <p class="muted" id="summary-reported-help">Los indicadores, lugares y demás opciones corresponden al estado elegido. Al cambiarlo se actualiza el informe y se limpian los demás filtros.</p>
    </form>
</section>

<section class="content-card filter-card">
    <form method="get" class="beneficiary-report-filters" id="beneficiary-report-filters" data-locations-url="{{ route('beneficiaries.locations') }}">
        <input type="hidden" name="reported" value="{{ $filters['reported'] ?? '' }}">
        <label>Fecha de atención desde
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </label>
        <label>Fecha de atención hasta
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </label><br>
        <label>Fecha de registro (inclusión) desde
            <input type="date" name="included_from" value="{{ $filters['included_from'] ?? '' }}">
        </label>
        <label>Fecha de registro (inclusión) hasta
            <input type="date" name="included_to" value="{{ $filters['included_to'] ?? '' }}">
        </label><br>
        <label>Estado
            <select name="state_id" id="summary_state_id"><option value="">Todos</option>@foreach ($states as $state)<option value="{{ $state->id }}" @selected(($filters['state_id'] ?? '') == $state->id)>{{ $state->name }}</option>@endforeach</select>
        </label>
        <label>Municipio
            <select name="municipality_id" id="summary_municipality_id"><option value="">Todos</option>@foreach ($municipalities as $municipality)<option value="{{ $municipality->id }}" @selected(($filters['municipality_id'] ?? '') == $municipality->id)>{{ $municipality->name }}</option>@endforeach</select>
        </label>
        <label>Parroquia
            <select name="parish_id" id="summary_parish_id"><option value="">Todas</option>@foreach ($parishes as $parish)<option value="{{ $parish->id }}" @selected(($filters['parish_id'] ?? '') == $parish->id)>{{ $parish->name }}</option>@endforeach</select>
        </label>
        <label>Tipo de instalación
            <select name="installation_type"><option value="">Todos</option>@foreach ($installationTypes as $type)<option value="{{ $type }}" @selected(($filters['installation_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select>
        </label>
        <label>Nombre del lugar
            <select name="place_name"><option value="">Todos</option>@foreach ($places as $place)<option value="{{ $place }}" @selected(($filters['place_name'] ?? '') === $place)>{{ $place }}</option>@endforeach</select>
        </label>
        <label>Sector programático
            <select name="sector_id" id="summary_sector_id"><option value="">Todos</option>@foreach ($sectors as $sector)<option value="{{ $sector->id }}" @selected(($filters['sector_id'] ?? '') == $sector->id)>{{ $sector->name }}</option>@endforeach</select>
        </label>
        @include('beneficiaries.partials.indicator-picker')
        <label>Recurrente
            <select name="is_recurrent"><option value="">Todos</option>@if(in_array('1', $recurrenceOptions, true))<option value="1" @selected(($filters['is_recurrent'] ?? '') === '1')>Sí</option>@endif @if(in_array('0', $recurrenceOptions, true))<option value="0" @selected(($filters['is_recurrent'] ?? '') === '0')>No</option>@endif</select>
        </label>
        <div id="summary-locations-error" role="alert" hidden>
            No se pudieron cargar las ubicaciones. <button type="button" id="summary-locations-retry">Reintentar</button>
        </div>
        <div class="filter-actions">
            <button class="button button-primary" type="submit">Generar informe</button>
            @can('exportar registros excel')
                <a class="button button-excel" id="beneficiary-export-button"
                    data-export-url="{{ route('beneficiaries.export') }}"
                    href="{{ route('beneficiaries.export', request()->query()) }}">
                    <svg class="excel-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M13 3h8v18h-8v-3h5v-2h-5v-2h5v-2h-5v-2h5V8h-5V6h5V5h-5V3Z" fill="currentColor" opacity=".72"/>
                        <path d="M3 5.2 14 3v18L3 18.8V5.2Zm3.2 3.1 2 3.6-2.2 3.8h2.1l1.2-2.3 1.3 2.3h2.1l-2.2-3.9 2-3.5h-2L9.4 10.4 8.2 8.3h-2Z" fill="currentColor"/>
                    </svg>
                    Exportar Excel
                </a>
            @endcan
            <a class="button button-secondary" href="{{ route('beneficiaries.summary', ['reported' => $filters['reported'] ?? '']) }}">Limpiar</a>
        </div>
    </form>
</section>

<section class="content-card" id="beneficiary-groups-section">
    <div class="card-heading"><div><h2>Beneficiarios por atención</h2><p class="muted">{{ number_format($groupedBeneficiaries->count()) }} {{ $groupedBeneficiaries->count() === 1 ? 'grupo coincide' : 'grupos coinciden' }} con los filtros seleccionados.</p></div></div>
    @if($groupedBeneficiaries->isEmpty())
        <div class="empty-state"><p>No hay beneficiarios que coincidan con los filtros.</p></div>
    @else
        <div class="table-wrap"><table id="beneficiary-attention-table" class="beneficiary-attention-table">
            <thead><tr><th>Fecha de atención</th><th>Estado</th><th>Municipio</th><th>Parroquia</th><th>Nombre del lugar</th><th>Sector</th><th>Indicador</th>@if($showReportedAt)<th>Fecha de reporte</th>@endif<th>Beneficiarios</th></tr></thead>
            <tbody>@foreach($groupedBeneficiaries as $group)
                <tr><td data-order="{{ $group->report_date }}">{{ \Illuminate\Support\Carbon::parse($group->report_date)->format('d/m/Y') }}</td><td>{{ $group->state_name }}</td><td>{{ $group->municipality_name }}</td><td>{{ $group->parish_name }}</td><td>{{ $group->place_name }}</td><td>{{ $group->project_sector_name }}</td><td>{{ $group->activity_title }}</td>@if($showReportedAt)<td data-order="{{ $group->reported_at }}">{{ \Illuminate\Support\Carbon::parse($group->reported_at)->format('d/m/Y') }}</td>@endif<td data-order="{{ $group->beneficiary_count }}">{{ number_format($group->beneficiary_count) }}</td></tr>
            @endforeach</tbody>
        </table></div>
    @endif
</section>

<nav class="report-result-tabs" role="tablist" aria-label="Tipo de resultado">
    <button type="button" class="report-result-tab is-active" id="kobo-tab" role="tab" aria-selected="true" aria-controls="kobo-panel" data-report-tab="kobo-panel">Resultado KOBO</button>
    <button type="button" class="report-result-tab" id="345w-tab" role="tab" aria-selected="false" aria-controls="345w-panel" data-report-tab="345w-panel">Resultado 345W</button>
</nav>

<div class="report-result-panel" id="kobo-panel" role="tabpanel" aria-labelledby="kobo-tab">
<section class="content-card summary-card" id="beneficiary-results-section">
    <div class="card-heading"><div><h2>Resultados</h2><p class="muted">{{ number_format($reportCount) }} {{ $reportCount === 1 ? 'registro coincide' : 'registros coinciden' }} con los filtros seleccionados.</p></div></div>
    @php
        $totalNna = $summary['girls_0_5'] + $summary['boys_0_5']
            + $summary['girls_6_9'] + $summary['boys_6_9']
            + $summary['girls_10_11'] + $summary['boys_10_11']
            + $summary['girls_12_14'] + $summary['boys_12_14']
            + $summary['girls_15_17'] + $summary['boys_15_17'];
        $totalAdults = $summary['women_18_19'] + $summary['men_18_19']
            + $summary['women_20_49'] + $summary['men_20_49']
            + $summary['women_50_59'] + $summary['men_50_59']
            + $summary['women_60_plus'] + $summary['men_60_plus'];
    @endphp
    <div class="table-wrap"><table class="summary-table">
        <thead><tr><th>Beneficiarios</th><th>Cantidad</th></tr></thead>
        <tbody>
            <tr><td>Niñas de 0 a 5 años</td><td>{{ number_format($summary['girls_0_5']) }}</td></tr>
            <tr><td>Niños de 0 a 5 años</td><td>{{ number_format($summary['boys_0_5']) }}</td></tr>
            <tr><td>Niñas de 6 a 9 años</td><td>{{ number_format($summary['girls_6_9']) }}</td></tr>
            <tr><td>Niños de 6 a 9 años</td><td>{{ number_format($summary['boys_6_9']) }}</td></tr>
            <tr><td>Niñas de 10 a 11 años</td><td>{{ number_format($summary['girls_10_11']) }}</td></tr>
            <tr><td>Niños de 10 a 11 años</td><td>{{ number_format($summary['boys_10_11']) }}</td></tr>
            <tr><td>Niñas de 12 a 14 años</td><td>{{ number_format($summary['girls_12_14']) }}</td></tr>
            <tr><td>Niños de 12 a 14 años</td><td>{{ number_format($summary['boys_12_14']) }}</td></tr>
            <tr><td>Niñas de 15 a 17 años</td><td>{{ number_format($summary['girls_15_17']) }}</td></tr>
            <tr><td>Niños de 15 a 17 años</td><td>{{ number_format($summary['boys_15_17']) }}</td></tr>
            <tr class="summary-subtotal"><th>Total NNA</th><th>{{ number_format($totalNna) }}</th></tr>
            <tr><td>Mujeres de 18 a 19 años</td><td>{{ number_format($summary['women_18_19']) }}</td></tr>
            <tr><td>Hombres de 18 a 19 años</td><td>{{ number_format($summary['men_18_19']) }}</td></tr>
            <tr><td>Mujeres de 20 a 49 años</td><td>{{ number_format($summary['women_20_49']) }}</td></tr>
            <tr><td>Hombres de 20 a 49 años</td><td>{{ number_format($summary['men_20_49']) }}</td></tr>
            <tr><td>Mujeres de 50 a 59 años</td><td>{{ number_format($summary['women_50_59']) }}</td></tr>
            <tr><td>Hombres de 50 a 59 años</td><td>{{ number_format($summary['men_50_59']) }}</td></tr>
            <tr><td>Mujeres de 60 años o más</td><td>{{ number_format($summary['women_60_plus']) }}</td></tr>
            <tr><td>Hombres de 60 años o más</td><td>{{ number_format($summary['men_60_plus']) }}</td></tr>
            <tr class="summary-subtotal"><th>Total de adultos</th><th>{{ number_format($totalAdults) }}</th></tr>
        </tbody>
        <tfoot>
            <tr><th>Total de beneficiarios alcanzados</th><th>{{ number_format($summary['total']) }}</th></tr>
            <tr><td>Personas con discapacidad</td><td>{{ number_format($summary['disability']) }}</td></tr>
            <tr><td>Población indígena</td><td>{{ number_format($summary['ethnicity']) }}</td></tr>
            <tr><td>Mujeres embarazadas o en lactancia</td><td>{{ number_format($summary['pregnancy']) }}</td></tr>
        </tfoot>
    </table></div>
</section>

@if((string) ($filters['reported'] ?? '') !== '1' && auth()->user()->canMarkAsReported())
    <section class="content-card donor-report-card" id="donor-report-section">
        <div><h2>Reporte al donante</h2><p class="muted">Indique la fecha con la que se consolidará la información actualmente filtrada.</p></div>
        @if($pendingBeneficiaryCount > 0)
            <form method="post" action="{{ route('beneficiaries.mark-reported') }}" class="donor-report-form" data-beneficiary-count="{{ $pendingBeneficiaryCount }}" data-can-report="1">
                @csrf
                @foreach($filters as $name => $value)
                    @foreach(is_array($value) ? $value : [$value] as $item)
                        @if($item !== null && $item !== '')<input type="hidden" name="{{ $name }}{{ is_array($value) ? '[]' : '' }}" value="{{ $item }}">@endif
                    @endforeach
                @endforeach
                <label>Fecha de reporte *<input type="date" name="reported_at" value="{{ today()->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" required></label>
                <button class="button button-primary" type="submit">Actualizar a Reportado</button>
            </form>
        @else
            <p class="muted">No hay beneficiarios pendientes de reporte con los filtros actuales.</p>
        @endif
    </section>
@endif
</div>

<div class="report-result-panel" id="345w-panel" role="tabpanel" aria-labelledby="345w-tab" hidden>
    <div id="beneficiary-results-345w">
        @include('beneficiaries.partials.results-345w')
    </div>
</div>

<script>
const summarySelect = (id) => document.getElementById(id);
const reportedFilterForm = summarySelect('beneficiary-reported-filter');
summarySelect('summary_reported').addEventListener('change', () => reportedFilterForm.requestSubmit());
const beneficiaryFilterForm = document.getElementById('beneficiary-report-filters');
const beneficiaryExportButton = document.getElementById('beneficiary-export-button');
const syncBeneficiaryExportUrl = () => {
    if (!beneficiaryExportButton) return;
    const exportUrl = new URL(beneficiaryExportButton.dataset.exportUrl, window.location.origin);
    exportUrl.search = new URLSearchParams(new FormData(beneficiaryFilterForm)).toString();
    beneficiaryExportButton.href = exportUrl.toString();
};
beneficiaryFilterForm.addEventListener('change', syncBeneficiaryExportUrl);
beneficiaryFilterForm.addEventListener('input', syncBeneficiaryExportUrl);
beneficiaryExportButton?.addEventListener('click', syncBeneficiaryExportUrl);
const activateReportTab = (tab) => {
    document.querySelectorAll('[data-report-tab]').forEach(button => {
        const isActive = button === tab;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-selected', isActive ? 'true' : 'false');
        document.getElementById(button.dataset.reportTab).hidden = !isActive;
    });
};
document.querySelectorAll('[data-report-tab]').forEach(tab => tab.addEventListener('click', () => activateReportTab(tab)));
</script>

<script src="{{ asset('js/beneficiary-indicator-picker.js') }}?v={{ filemtime(public_path('js/beneficiary-indicator-picker.js')) }}" defer></script>
<script src="{{ asset('js/general-report-locations.js') }}?v={{ filemtime(public_path('js/general-report-locations.js')) }}"></script>
<script src="/vendor/datatables/jquery-3.7.1.min.js"></script>
<script src="/vendor/datatables/dataTables.min.js"></script>
<script src="/vendor/datatables/dataTables.buttons.min.js"></script>
<script src="/vendor/datatables/jszip.min.js"></script>
<script src="/vendor/datatables/pdfmake.min.js"></script>
<script src="/vendor/datatables/vfs_fonts.js"></script>
<script src="/vendor/datatables/buttons.html5.min.js"></script>
<script src="/vendor/datatables/buttons.print.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const initializeBeneficiaryTable = () => {
        const beneficiaryAttentionTable = document.getElementById('beneficiary-attention-table');
        if (!beneficiaryAttentionTable || typeof DataTable === 'undefined') return;

        new DataTable(beneficiaryAttentionTable, {
            layout: {
                topStart: {
                    buttons: [
                        {extend: 'copyHtml5', text: 'Copiar'},
                        {extend: 'csvHtml5', text: 'CSV', title: 'Beneficiarios por atención'},
                        @can('exportar registros excel'){extend: 'excelHtml5', text: 'Excel', title: 'Beneficiarios por atención'},@endcan
                        @can('exportar registros pdf'){extend: 'pdfHtml5', text: 'PDF', title: 'Beneficiarios por atención', orientation: 'landscape', pageSize: 'A4'},@endcan
                        {extend: 'print', text: 'Imprimir', title: 'Beneficiarios por atención'},
                    ],
                },
                topEnd: 'search',
                bottomStart: 'info',
                bottomEnd: 'paging',
            },
            pageLength: 10,
            lengthMenu: [[10, 25, 50, -1], [10, 25, 50, 'Todos']],
            order: [[0, 'desc']],
            language: {
                emptyTable: 'No hay beneficiarios que coincidan con los filtros.',
                info: 'Mostrando _START_ a _END_ de _TOTAL_ grupos',
                infoEmpty: 'Mostrando 0 a 0 de 0 grupos',
                infoFiltered: '(filtrado de _MAX_ grupos)',
                lengthMenu: 'Mostrar _MENU_ grupos',
                loadingRecords: 'Cargando…',
                processing: 'Procesando…',
                search: 'Buscar:',
                zeroRecords: 'No se encontraron grupos coincidentes',
                paginate: {first: 'Primero', last: 'Último', next: 'Siguiente', previous: 'Anterior'},
            },
        });
    };

    const reportDocument = async (url, options = {}) => {
        const response = await fetch(url, {...options, headers: {...(options.headers || {}), 'X-Requested-With': 'XMLHttpRequest'}});
        if (!response.ok) throw new Error('No fue posible actualizar el informe.');
        return new DOMParser().parseFromString(await response.text(), 'text/html');
    };

    const replaceReportSection = (source, id) => {
        const current = document.getElementById(id);
        const replacement = source.getElementById(id);
        if (current && replacement) current.replaceWith(replacement);
        else if (current) current.remove();
    };

    document.addEventListener('click', async (event) => {
        const submitButton = event.target.closest('#donor-report-section button[type="submit"]');
        if (!submitButton) return;
        const form = submitButton.form;
        event.preventDefault();
        if (form.dataset.canReport !== '1') {
            const authorizationMessage = 'Solo los Coordinadores o Administradores pueden Reportar';
            if (typeof Swal !== 'undefined') {
                await Swal.fire({
                    title: 'Acción no permitida',
                    text: authorizationMessage,
                    icon: 'warning',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#1cabe2',
                });
            } else {
                window.alert(authorizationMessage);
            }
            return;
        }
        if (!form.reportValidity()) return;

        const beneficiaryCount = Number(form.dataset.beneficiaryCount || 0);
        const confirmationText = `Se actualizarán ${beneficiaryCount.toLocaleString('es-VE')} ${beneficiaryCount === 1 ? 'beneficiario pendiente' : 'beneficiarios pendientes'} que coinciden con los filtros actuales.`;
        const confirmation = typeof Swal !== 'undefined'
            ? await Swal.fire({
                title: '¿Actualizar a Reportado?',
                text: confirmationText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, actualizar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#1cabe2',
                cancelButtonColor: '#6c757d',
                reverseButtons: true,
                focusCancel: true,
            })
            : {isConfirmed: window.confirm(confirmationText)};
        if (!confirmation.isConfirmed) return;

        submitButton.disabled = true;
        try {
            await reportDocument(form.action, {method: 'POST', body: new FormData(form)});
            const baseUrl = new URL(document.getElementById('beneficiary-report-filters').action || window.location.href);
            baseUrl.search = new URLSearchParams(new FormData(document.getElementById('beneficiary-report-filters'))).toString();
            const source = await reportDocument(baseUrl.toString());
            replaceReportSection(source, 'beneficiary-groups-section');
            replaceReportSection(source, 'donor-report-section');
            replaceReportSection(source, 'beneficiary-results-section');
            replaceReportSection(source, 'beneficiary-results-345w');
            initializeBeneficiaryTable();

            if (typeof Swal !== 'undefined') {
                await Swal.fire({
                    title: 'Actualización completada',
                    text: beneficiaryCount === 1 ? 'El beneficiario fue marcado como reportado.' : 'Los beneficiarios fueron marcados como reportados.',
                    icon: 'success',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#1cabe2',
                });
            }
        } catch (error) {
            if (typeof Swal !== 'undefined') {
                await Swal.fire({
                    title: 'No se pudo actualizar',
                    text: 'Intente nuevamente. Si el problema continúa, recargue la página.',
                    icon: 'error',
                    confirmButtonText: 'Aceptar',
                    confirmButtonColor: '#1cabe2',
                });
            }
        } finally {
            submitButton.disabled = false;
        }
    });

    initializeBeneficiaryTable();
</script>
@endsection
