@extends('layouts.app')

@section('title', 'Informes generales | Respuesta ASONACOP')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/general-reports.css') }}">
@endpush

@section('content')
<section class="page-heading compact-heading general-report-heading">
    <div>
        <p class="eyebrow">Consolidado de respuesta</p>
        <h1>Informes Generales</h1>
        <p class="muted">Analice la poblaci&oacute;n atendida mediante filtros y gr&aacute;ficos interactivos.</p>
    </div>
</section>

<section class="card general-filter-card">
    <div class="card-header"><div><h2 class="card-title mb-1">Filtros del informe</h2><p class="text-muted mb-0">Combine uno o varios criterios para actualizar todos los resultados.</p></div></div>
    <div class="card-body">
        <form method="get" id="general-report-filters" class="row g-3">
            <div class="col-xl-3 col-md-6"><label class="form-label">Fecha de atenci&oacute;n desde</label><input class="form-control" type="date" name="attention_from" value="{{ $filters['attention_from'] ?? '' }}"></div>
            <div class="col-xl-3 col-md-6"><label class="form-label">Fecha de atenci&oacute;n hasta</label><input class="form-control" type="date" name="attention_to" value="{{ $filters['attention_to'] ?? '' }}"></div>
            <div class="col-xl-3 col-md-6"><label class="form-label">Fecha de registro desde</label><input class="form-control" type="date" name="registered_from" value="{{ $filters['registered_from'] ?? '' }}"></div>
            <div class="col-xl-3 col-md-6"><label class="form-label">Fecha de registro hasta</label><input class="form-control" type="date" name="registered_to" value="{{ $filters['registered_to'] ?? '' }}"></div>

            <div class="col-xl-2 col-md-4"><label class="form-label">Edad desde</label><input class="form-control" type="number" name="age_from" min="0" max="120" value="{{ $filters['age_from'] ?? '' }}" placeholder="0"></div>
            <div class="col-xl-2 col-md-4"><label class="form-label">Edad hasta</label><input class="form-control" type="number" name="age_to" min="0" max="120" value="{{ $filters['age_to'] ?? '' }}" placeholder="120"></div>
            <div class="col-xl-4 col-md-4"><label class="form-label">Grupo etario</label><select class="form-select" name="age_group"><option value="">Todos</option>@foreach($ageGroups as $value => $group)<option value="{{ $value }}" @selected(($filters['age_group'] ?? '') === $value)>{{ $group['label'] }}</option>@endforeach</select></div>
            <div class="col-xl-4 col-md-6"><label class="form-label">Sexo</label><select class="form-select" name="sex"><option value="">Todos</option>@foreach(config('reports.beneficiary_options.sexes') as $sex)<option value="{{ $sex }}" @selected(($filters['sex'] ?? '') === $sex)>{{ $sex }}</option>@endforeach</select></div>

            <div class="col-xl-4 col-md-6"><label class="form-label">Estado</label><select class="form-select" name="state_id" id="general_state_id"><option value="">Todos</option>@foreach($states as $state)<option value="{{ $state->id }}" @selected(($filters['state_id'] ?? '') == $state->id)>{{ $state->name }}</option>@endforeach</select></div>
            <div class="col-xl-4 col-md-6"><label class="form-label">Municipio</label><select class="form-select" name="municipality_id" id="general_municipality_id"><option value="">Todos</option>@foreach($municipalities as $municipality)<option value="{{ $municipality->id }}" @selected(($filters['municipality_id'] ?? '') == $municipality->id)>{{ $municipality->name }}</option>@endforeach</select></div>
            <div class="col-xl-4 col-md-6"><label class="form-label">Parroquia</label><select class="form-select" name="parish_id" id="general_parish_id"><option value="">Todas</option>@foreach($parishes as $parish)<option value="{{ $parish->id }}" @selected(($filters['parish_id'] ?? '') == $parish->id)>{{ $parish->name }}</option>@endforeach</select></div>

            <div class="col-xl-4 col-md-6"><label class="form-label">Tipo de atenci&oacute;n</label><select class="form-select" name="installation_type"><option value="">Todos</option>@foreach($installationTypes as $type)<option value="{{ $type }}" @selected(($filters['installation_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
            <div class="col-xl-4 col-md-6"><label class="form-label">Nombre del lugar</label><select class="form-select" name="place_name"><option value="">Todos</option>@foreach($places as $place)<option value="{{ $place }}" @selected(($filters['place_name'] ?? '') === $place)>{{ $place }}</option>@endforeach</select></div>
            <div class="col-xl-4 col-md-6"><label class="form-label">Sector program&aacute;tico</label><select class="form-select" name="sector_id" id="general_sector_id"><option value="">Todos</option>@foreach($sectors as $sector)<option value="{{ $sector->id }}" @selected(($filters['sector_id'] ?? '') == $sector->id)>{{ $sector->name }}</option>@endforeach</select></div>
            <div class="col-xl-4 col-md-6"><label class="form-label">Indicador a reportar</label><select class="form-select" name="activity_id" id="general_activity_id"><option value="">Todos</option>@foreach($activities as $activity)<option value="{{ $activity->id }}" @selected(($filters['activity_id'] ?? '') == $activity->id)>{{ $activity->title }}</option>@endforeach</select></div>
            <div class="col-xl-4 col-md-6"><label class="form-label">Recurrente</label><select class="form-select" name="is_recurrent"><option value="">Todos</option><option value="1" @selected(($filters['is_recurrent'] ?? '') === '1')>S&iacute;</option><option value="0" @selected(($filters['is_recurrent'] ?? '') === '0')>No</option></select></div>
            <div class="col-xl-4 col-md-6"><label class="form-label">Reportado</label><select class="form-select" name="reported"><option value="">Todos</option><option value="1" @selected(($filters['reported'] ?? '') === '1')>S&iacute;</option><option value="0" @selected(($filters['reported'] ?? '') === '0')>No</option></select></div>

            <div class="col-12 d-flex flex-wrap justify-content-end gap-2 pt-2">
                <a class="btn btn-light" href="{{ route('general-reports.index') }}"><i class="ri-refresh-line me-1"></i>Limpiar</a>
                <button class="btn btn-primary" type="submit"><i class="ri-filter-3-line me-1"></i>Aplicar filtros</button>
            </div>
        </form>
    </div>
</section>

<div class="row general-kpis">
    @foreach([
        ['Personas atendidas', $summary['beneficiaries'], 'ri-group-line', 'primary'],
        ['Registros de atenci&oacute;n', $summary['attentions'], 'ri-file-list-3-line', 'info'],
        ['Hombres', $summary['men'], 'ri-men-line', 'indigo'],
        ['Mujeres', $summary['women'], 'ri-women-line', 'danger'],
        ['Edad promedio', number_format($summary['average_age'], 1, ',', '.').' a&ntilde;os', 'ri-calendar-event-line', 'success'],
    ] as [$label, $value, $icon, $tone])
    <div class="col-xl col-md-4 col-sm-6"><article class="card general-kpi-card"><div class="card-body"><div><p>{!! $label !!}</p><strong>{!! $value !!}</strong></div><span class="general-kpi-icon tone-{{ $tone }}"><i class="{{ $icon }}"></i></span></div></article></div>
    @endforeach
</div>

@if($summary['beneficiaries'] === 0)
<section class="card"><div class="card-body general-empty"><i class="ri-bar-chart-box-line"></i><h2>Sin resultados</h2><p>Modifique los filtros para visualizar informaci&oacute;n.</p></div></section>
@else
<div class="row">
    <div class="col-xl-8"><section class="card general-chart-card"><div class="card-header"><h2 class="card-title mb-1">Beneficiarios por grupo etario y sexo</h2><p class="text-muted mb-0">Comparaci&oacute;n de hombres y mujeres en cada rango de edad.</p></div><div class="card-body"><div id="general-age-chart" class="general-chart"></div></div></section></div>
    <div class="col-xl-4"><section class="card general-chart-card"><div class="card-header"><h2 class="card-title mb-1">Distribuci&oacute;n por sexo</h2><p class="text-muted mb-0">Participaci&oacute;n sobre el total filtrado.</p></div><div class="card-body"><div id="general-sex-chart" class="general-chart"></div></div></section></div>
    <div class="col-xl-6"><section class="card general-chart-card"><div class="card-header"><h2 class="card-title mb-1">Tipo de atenci&oacute;n</h2><p class="text-muted mb-0">Beneficiarios según el tipo de espacio o instalaci&oacute;n.</p></div><div class="card-body"><div id="general-attention-chart" class="general-chart"></div></div></section></div>
    <div class="col-xl-6"><section class="card general-chart-card"><div class="card-header"><h2 class="card-title mb-1">Distribuci&oacute;n territorial</h2><p class="text-muted mb-0">Los diez estados con mayor cantidad de beneficiarios.</p></div><div class="card-body"><div id="general-state-chart" class="general-chart"></div></div></section></div>
    <div class="col-12"><section class="card general-chart-card general-trend-card"><div class="card-header"><h2 class="card-title mb-1">Evoluci&oacute;n de atenciones</h2><p class="text-muted mb-0">Hombres y mujeres atendidos seg&uacute;n la fecha de atenci&oacute;n.</p></div><div class="card-body"><div id="general-trend-chart" class="general-chart general-chart-wide"></div></div></section></div>
</div>
@endif
@endsection

@push('scripts')
<script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const chartData = {{ Illuminate\Support\Js::from($charts) }};
    const palette = {blue: '#405189', cyan: '#299cdb', teal: '#0ab39c', orange: '#f7b84b', red: '#f06548', purple: '#6559cc'};
    const render = (selector, options) => { const element = document.querySelector(selector); if (element && typeof ApexCharts !== 'undefined') new ApexCharts(element, options).render(); };
    const shared = {chart: {fontFamily: 'inherit', toolbar: {show: false}}, dataLabels: {enabled: false}, legend: {position: 'bottom'}, noData: {text: 'Sin datos para mostrar'}};

    render('#general-age-chart', {...shared, series: [{name: 'Hombres', data: chartData.ages.men}, {name: 'Mujeres', data: chartData.ages.women}], chart: {...shared.chart, type: 'bar', height: 355, stacked: false}, colors: [palette.blue, palette.cyan], plotOptions: {bar: {horizontal: false, columnWidth: '52%', borderRadius: 4}}, xaxis: {categories: chartData.ages.labels, labels: {rotate: -30}}, yaxis: {min: 0, forceNiceScale: true}, tooltip: {shared: true, intersect: false}});
    render('#general-sex-chart', {...shared, series: chartData.sex.values, labels: chartData.sex.labels, chart: {...shared.chart, type: 'donut', height: 355}, colors: [palette.blue, palette.cyan], dataLabels: {enabled: true}, plotOptions: {pie: {donut: {size: '67%', labels: {show: true, total: {show: true, label: 'Total', formatter: () => '{{ number_format($summary['beneficiaries']) }}'}}}}}});
    render('#general-attention-chart', {...shared, series: chartData.attention_types.values, labels: chartData.attention_types.labels, chart: {...shared.chart, type: 'pie', height: 355}, colors: [palette.blue, palette.teal, palette.orange, palette.cyan, palette.red, palette.purple], dataLabels: {enabled: true}, responsive: [{breakpoint: 600, options: {chart: {height: 410}, legend: {position: 'bottom'}}}]});
    render('#general-state-chart', {...shared, series: [{name: 'Beneficiarios', data: chartData.states.values}], chart: {...shared.chart, type: 'bar', height: 355}, colors: [palette.teal], plotOptions: {bar: {horizontal: true, borderRadius: 4, barHeight: '58%'}}, xaxis: {categories: chartData.states.labels, min: 0}});
    render('#general-trend-chart', {...shared, series: [{name: 'Hombres', data: chartData.trend.men}, {name: 'Mujeres', data: chartData.trend.women}], chart: {...shared.chart, type: 'area', height: 365, zoom: {enabled: false}}, colors: [palette.blue, palette.cyan], stroke: {curve: 'smooth', width: 3}, fill: {type: 'gradient', gradient: {opacityFrom: .28, opacityTo: .04}}, xaxis: {categories: chartData.trend.labels, type: 'datetime'}, markers: {size: 3}, tooltip: {shared: true, intersect: false, x: {format: 'dd/MM/yyyy'}}});

    const select = id => document.getElementById(id);
    const state = select('general_state_id'), municipality = select('general_municipality_id'), parish = select('general_parish_id'), sector = select('general_sector_id'), activity = select('general_activity_id');
    const fillOptions = (element, items, placeholder) => { element.innerHTML = `<option value="">${placeholder}</option>` + items.map(item => `<option value="${item.id}">${item.name || item.title}</option>`).join(''); };
    const load = async (element, url, placeholder) => { const response = await fetch(url, {headers: {'Accept': 'application/json'}}); if (!response.ok) throw new Error('No se pudieron cargar las opciones'); fillOptions(element, await response.json(), placeholder); };
    state?.addEventListener('change', async () => { fillOptions(municipality, [], state.value ? 'Cargando...' : 'Todos'); fillOptions(parish, [], 'Todas'); if (state.value) await load(municipality, `/ubicaciones/estados/${state.value}/municipios`, 'Todos'); });
    municipality?.addEventListener('change', async () => { fillOptions(parish, [], municipality.value ? 'Cargando...' : 'Todas'); if (municipality.value) await load(parish, `/ubicaciones/municipios/${municipality.value}/parroquias`, 'Todas'); });
    sector?.addEventListener('change', async () => { fillOptions(activity, [], 'Cargando...'); await load(activity, sector.value ? `/sectores/${sector.value}/actividades` : `{{ route('activities.all') }}`, 'Todos'); });
});
</script>
@endpush
