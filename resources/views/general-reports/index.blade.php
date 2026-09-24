@extends('layouts.app')

@section('title', 'Informes generales | SIA')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/general-reports.css') }}?v={{ filemtime(public_path('css/general-reports.css')) }}">
@endpush

@section('content')
<section class="page-heading compact-heading general-report-heading">
    <div>
        <p class="eyebrow">Consolidado de respuesta</p>
        <h1>Informes Generales</h1>
        <p class="muted">Analice la poblaci&oacute;n atendida mediante filtros y gr&aacute;ficos interactivos.</p>
    </div>
</section>

@include('general-reports.partials.filters-and-summary')

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
<script src="{{ asset('js/general-report-locations.js') }}?v={{ filemtime(public_path('js/general-report-locations.js')) }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const chartData = {{ Illuminate\Support\Js::from($charts) }};
    const palette = {blue: '#405189', cyan: '#299cdb', teal: '#0ab39c', orange: '#f7b84b', red: '#f06548', purple: '#6559cc'};
    const render = (selector, options) => { const element = document.querySelector(selector); if (element && typeof ApexCharts !== 'undefined') new ApexCharts(element, options).render(); };
    const shared = {chart: {fontFamily: 'inherit', toolbar: {show: false}}, dataLabels: {enabled: false}, legend: {position: 'bottom'}, noData: {text: 'Sin datos para mostrar'}};

    render('#general-age-chart', {
        ...shared,
        series: [{name: 'Hombres', data: chartData.ages.men}, {name: 'Mujeres', data: chartData.ages.women}],
        chart: {...shared.chart, type: 'bar', height: 355, stacked: false},
        colors: [palette.blue, palette.cyan],
        plotOptions: {bar: {horizontal: false, columnWidth: '52%', borderRadius: 4, dataLabels: {position: 'top'}}},
        dataLabels: {
            enabled: true,
            formatter: value => Number(value).toLocaleString('es-VE'),
            offsetY: -20,
            style: {fontSize: '11px', fontWeight: 600, colors: [palette.blue]},
            background: {enabled: true, foreColor: '#fff', borderRadius: 3, padding: 3, opacity: 1, borderWidth: 0},
        },
        xaxis: {categories: chartData.ages.labels, labels: {rotate: -30}},
        yaxis: {min: 0, max: value => Math.max(1, Math.ceil(value * 1.15)), forceNiceScale: true},
        grid: {padding: {top: 15}},
        tooltip: {shared: true, intersect: false},
    });
    render('#general-sex-chart', {...shared, series: chartData.sex.values, labels: chartData.sex.labels, chart: {...shared.chart, type: 'donut', height: 355}, colors: [palette.blue, palette.cyan], dataLabels: {enabled: true}, plotOptions: {pie: {donut: {size: '67%', labels: {show: true, total: {show: true, label: 'Total', formatter: () => '{{ number_format($summary['beneficiaries']) }}'}}}}}});
    render('#general-attention-chart', {...shared, series: chartData.attention_types.values, labels: chartData.attention_types.labels, chart: {...shared.chart, type: 'pie', height: 355}, colors: [palette.blue, palette.teal, palette.orange, palette.cyan, palette.red, palette.purple], dataLabels: {enabled: true}, responsive: [{breakpoint: 600, options: {chart: {height: 410}, legend: {position: 'bottom'}}}]});
    render('#general-state-chart', {...shared, series: [{name: 'Beneficiarios', data: chartData.states.values}], chart: {...shared.chart, type: 'bar', height: 355}, colors: [palette.teal], dataLabels: {enabled: true, formatter: value => Number(value).toLocaleString('es-VE'), offsetX: 8, style: {fontSize: '12px', colors: ['#334155']}}, plotOptions: {bar: {horizontal: true, borderRadius: 4, barHeight: '58%', dataLabels: {position: 'top'}}}, xaxis: {categories: chartData.states.labels, min: 0}});
    render('#general-trend-chart', {...shared, series: [{name: 'Hombres', data: chartData.trend.men}, {name: 'Mujeres', data: chartData.trend.women}], chart: {...shared.chart, type: 'area', height: 365, zoom: {enabled: false}}, colors: [palette.blue, palette.cyan], stroke: {curve: 'smooth', width: 3}, fill: {type: 'gradient', gradient: {opacityFrom: .28, opacityTo: .04}}, dataLabels: {enabled: true, formatter: value => Number(value).toLocaleString('es-VE'), offsetY: -7, style: {fontSize: '10px'}, background: {enabled: true, borderRadius: 3, padding: 3, opacity: .85}}, xaxis: {categories: chartData.trend.labels, type: 'datetime'}, markers: {size: 4}, tooltip: {shared: true, intersect: false, x: {format: 'dd/MM/yyyy'}}});
});
</script>
@include('general-reports.partials.filter-scripts')
@endpush
