@extends('layouts.app')

@section('title', 'Informe de beneficiarios | Respuesta ASONACOP')

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

<section class="content-card filter-card">
    <form method="get" class="beneficiary-report-filters" id="beneficiary-report-filters">
        <label>Fecha de atención desde
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
        </label>
        <label>Fecha de atención hasta
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
        </label>
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
        <label>Actividad a reportar
            <select name="activity_id" id="summary_activity_id"><option value="">Todas</option>@foreach ($activities as $activity)<option value="{{ $activity->id }}" @selected(($filters['activity_id'] ?? '') == $activity->id)>{{ $activity->title }}</option>@endforeach</select>
        </label>
        <label>Recurrente
            <select name="is_recurrent"><option value="">Todos</option><option value="1" @selected(($filters['is_recurrent'] ?? '') === '1')>Sí</option><option value="0" @selected(($filters['is_recurrent'] ?? '') === '0')>No</option></select>
        </label>
        <label>Reportado
            <select name="reported"><option value="">Todos</option><option value="1" @selected(($filters['reported'] ?? '') === '1')>Sí</option><option value="0" @selected(($filters['reported'] ?? '') === '0')>No</option></select>
        </label>
        <div class="filter-actions">
            <button class="button button-primary" type="submit">Generar informe</button>
            <a class="button button-secondary" href="{{ route('beneficiaries.export', request()->query()) }}">Exportar Excel</a>
            <a class="button button-secondary" href="{{ route('beneficiaries.summary') }}">Limpiar</a>
        </div>
    </form>
</section>

<section class="content-card" id="beneficiary-groups-section">
    <div class="card-heading"><div><h2>Beneficiarios por atención</h2><p class="muted">{{ number_format($groupedBeneficiaries->count()) }} {{ $groupedBeneficiaries->count() === 1 ? 'grupo coincide' : 'grupos coinciden' }} con los filtros seleccionados.</p></div></div>
    @if($groupedBeneficiaries->isEmpty())
        <div class="empty-state"><p>No hay beneficiarios que coincidan con los filtros.</p></div>
    @else
        <div class="table-wrap"><table id="beneficiary-attention-table" class="beneficiary-attention-table">
            <thead><tr><th>Fecha de atención</th><th>Estado</th><th>Municipio</th><th>Parroquia</th><th>Nombre del lugar</th><th>Actividad</th>@if($showReportedAt)<th>Fecha de reporte</th>@endif<th>Beneficiarios</th></tr></thead>
            <tbody>@foreach($groupedBeneficiaries as $group)
                @php
                    $groupFilters = array_merge($filters, [
                        'from' => $group->report_date, 'to' => $group->report_date,
                        'state_id' => $group->state_id, 'municipality_id' => $group->municipality_id,
                        'parish_id' => $group->parish_id, 'place_name' => $group->place_name,
                        'activity_id' => $group->activity_id,
                    ]);
                    $groupUrl = route('beneficiaries.summary', array_filter($groupFilters, static fn ($value) => $value !== null && $value !== ''));
                @endphp
                <tr class="beneficiary-group-row" data-detail-url="{{ $groupUrl }}" tabindex="0" role="link" aria-label="Ver resultados del grupo del {{ \Illuminate\Support\Carbon::parse($group->report_date)->format('d/m/Y') }}"><td data-order="{{ $group->report_date }}"><a class="beneficiary-group-link" href="{{ $groupUrl }}">{{ \Illuminate\Support\Carbon::parse($group->report_date)->format('d/m/Y') }}</a></td><td>{{ $group->state_name }}</td><td>{{ $group->municipality_name }}</td><td>{{ $group->parish_name }}</td><td>{{ $group->place_name }}</td><td>{{ $group->activity_title }}</td>@if($showReportedAt)<td data-order="{{ $group->reported_at }}">{{ \Illuminate\Support\Carbon::parse($group->reported_at)->format('d/m/Y') }}</td>@endif<td data-order="{{ $group->beneficiary_count }}">{{ number_format($group->beneficiary_count) }}</td></tr>
            @endforeach</tbody>
        </table></div>
    @endif
</section>

<section class="content-card summary-card" id="beneficiary-results-section">
    <div class="card-heading"><div><h2>Resultados</h2><p class="muted">{{ number_format($reportCount) }} {{ $reportCount === 1 ? 'registro coincide' : 'registros coinciden' }} con los filtros seleccionados.</p></div></div>
    <div class="table-wrap"><table class="summary-table">
        <thead><tr><th>Beneficiarios</th><th>Cantidad</th></tr></thead>
        <tbody>
            <tr><td>Niñas (0 a 5 años)</td><td>{{ number_format($summary['girls_0_5']) }}</td></tr>
            <tr><td>Niños (0 a 5 años)</td><td>{{ number_format($summary['boys_0_5']) }}</td></tr>
            <tr><td>Niñas de 6 a 11 años</td><td>{{ number_format($summary['girls_6_11']) }}</td></tr>
            <tr><td>Niños de 6 a 11 años</td><td>{{ number_format($summary['boys_6_11']) }}</td></tr>
            <tr><td>Niñas de 12 a 17 años</td><td>{{ number_format($summary['girls_12_17']) }}</td></tr>
            <tr><td>Niños de 12 a 17 años</td><td>{{ number_format($summary['boys_12_17']) }}</td></tr>
            <tr><td>Mujeres (18 a 59 años)</td><td>{{ number_format($summary['women_18_59']) }}</td></tr>
            <tr><td>Hombres (18 a 59 años)</td><td>{{ number_format($summary['men_18_59']) }}</td></tr>
            <tr><td>Mujeres (60 años o más)</td><td>{{ number_format($summary['women_60_plus']) }}</td></tr>
            <tr><td>Hombres (60 años o más)</td><td>{{ number_format($summary['men_60_plus']) }}</td></tr>
        </tbody>
        <tfoot>
            <tr><th>Total de beneficiarios alcanzados</th><th>{{ number_format($summary['total']) }}</th></tr>
            <tr><td>Personas con discapacidad</td><td>{{ number_format($summary['disability']) }}</td></tr>
            <tr><td>Población indígena</td><td>{{ number_format($summary['ethnicity']) }}</td></tr>
            <tr><td>Mujeres embarazadas o en lactancia</td><td>{{ number_format($summary['pregnancy']) }}</td></tr>
        </tfoot>
    </table></div>
</section>

@if((string) ($filters['reported'] ?? '') !== '1')
    <section class="content-card donor-report-card" id="donor-report-section">
        <div><h2>Reporte al donante</h2><p class="muted">Indique la fecha con la que se consolidará la información actualmente filtrada.</p></div>
        @if($pendingBeneficiaryCount > 0)
            <form method="post" action="{{ route('beneficiaries.mark-reported') }}" class="donor-report-form" data-beneficiary-count="{{ $pendingBeneficiaryCount }}">
                @csrf
                @foreach($filters as $name => $value)
                    @if($value !== null && $value !== '')<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
                @endforeach
                <label>Fecha de reporte *<input type="date" name="reported_at" value="{{ today()->format('Y-m-d') }}" max="{{ today()->format('Y-m-d') }}" required></label>
                <button class="button button-primary" type="submit">Actualizar a Reportado</button>
            </form>
        @else
            <p class="muted">No hay beneficiarios pendientes de reporte con los filtros actuales.</p>
        @endif
    </section>
@endif

<script>
const summarySelect = (id) => document.getElementById(id);
const setSummaryOptions = (element, items, placeholder) => { element.innerHTML = `<option value="">${placeholder}</option>` + items.map(item => `<option value="${item.id}">${item.name || item.title}</option>`).join(''); };
const loadSummaryOptions = async (element, url, placeholder) => { const response = await fetch(url, {headers: {'Accept': 'application/json'}}); setSummaryOptions(element, await response.json(), placeholder); };
const summaryState = summarySelect('summary_state_id'), summaryMunicipality = summarySelect('summary_municipality_id'), summaryParish = summarySelect('summary_parish_id'), summarySector = summarySelect('summary_sector_id'), summaryActivity = summarySelect('summary_activity_id');
summaryState.addEventListener('change', async () => { setSummaryOptions(summaryMunicipality, [], 'Cargando municipios'); setSummaryOptions(summaryParish, [], 'Todas'); if (summaryState.value) await loadSummaryOptions(summaryMunicipality, `/ubicaciones/estados/${summaryState.value}/municipios`, 'Todos'); });
summaryMunicipality.addEventListener('change', async () => { setSummaryOptions(summaryParish, [], 'Cargando parroquias'); if (summaryMunicipality.value) await loadSummaryOptions(summaryParish, `/ubicaciones/municipios/${summaryMunicipality.value}/parroquias`, 'Todas'); });
summarySector.addEventListener('change', async () => { setSummaryOptions(summaryActivity, [], 'Cargando actividades'); await loadSummaryOptions(summaryActivity, summarySector.value ? `/sectores/${summarySector.value}/actividades` : `{{ route('activities.all') }}`, 'Todas'); });
</script>

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
                        {extend: 'excelHtml5', text: 'Excel', title: 'Beneficiarios por atención'},
                        {extend: 'pdfHtml5', text: 'PDF', title: 'Beneficiarios por atención', orientation: 'landscape', pageSize: 'A4'},
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

    const showGroupResults = async (url) => {
        document.body.classList.add('report-loading');
        try {
            const source = await reportDocument(url);
            replaceReportSection(source, 'donor-report-section');
            replaceReportSection(source, 'beneficiary-results-section');
            document.getElementById('beneficiary-results-section')?.scrollIntoView({behavior: 'smooth', block: 'start'});
        } catch (error) {
            window.location.href = url;
        } finally {
            document.body.classList.remove('report-loading');
        }
    };

    document.addEventListener('click', async (event) => {
        const row = event.target.closest('.beneficiary-group-row');
        if (row) {
            event.preventDefault();
            await showGroupResults(row.dataset.detailUrl);
            return;
        }

        const submitButton = event.target.closest('#donor-report-section button[type="submit"]');
        if (!submitButton) return;
        const form = submitButton.form;
        event.preventDefault();
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

    document.addEventListener('keydown', (event) => {
        const row = event.target.closest('.beneficiary-group-row');
        if (row && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            showGroupResults(row.dataset.detailUrl);
        }
    });

    initializeBeneficiaryTable();
</script>
@endsection
