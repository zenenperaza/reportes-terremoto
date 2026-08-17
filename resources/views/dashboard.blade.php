@extends('layouts.app')

@section('title', 'Panel | Respuesta ASONACOP')

@section('content')
<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <div>
                <h4 class="mb-1">{{ $isCoordinator ? 'Seguimiento de la respuesta' : 'Mis actividades registradas' }}</h4>
                <p class="text-muted mb-0">{{ $isCoordinator ? 'Seguimiento consolidado de registros y beneficiarios.' : 'Resumen de sus actividades y beneficiarios registrados.' }}</p>
            </div>
            <div class="page-title-right mt-3 mt-sm-0">
                <a class="btn btn-primary" href="{{ route('reports.create') }}"><i class="ri-add-circle-line align-middle me-1"></i> Registrar actividad</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate dashboard-stat-card">
            <div class="card-body">
                <div class="d-flex align-items-start"><div class="flex-grow-1"><p class="dashboard-stat-title text-uppercase text-muted mb-0">Registros</p></div><div class="flex-shrink-0"><span class="dashboard-stat-meta text-primary"><i class="ri-pulse-line me-1"></i>Actividades</span></div></div>
                <div class="d-flex align-items-end justify-content-between dashboard-stat-content"><div><h4 class="dashboard-stat-number ff-secondary"><span class="counter-value" data-target="{{ $reportCount }}">{{ number_format($reportCount) }}</span></h4><a href="{{ route('reports.index') }}" class="dashboard-stat-link">Ver registros</a></div><div class="dashboard-stat-icon dashboard-stat-icon-primary flex-shrink-0"><i class="ri-file-list-3-line"></i></div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate dashboard-stat-card">
            <div class="card-body">
                <div class="d-flex align-items-start"><div class="flex-grow-1"><p class="dashboard-stat-title text-uppercase text-muted mb-0">Beneficiarios alcanzados</p></div><div class="flex-shrink-0"><span class="dashboard-stat-meta text-info"><i class="ri-user-heart-line me-1"></i>Total</span></div></div>
                <div class="d-flex align-items-end justify-content-between dashboard-stat-content"><div><h4 class="dashboard-stat-number ff-secondary"><span class="counter-value" data-target="{{ $beneficiaryTotal }}">{{ number_format($beneficiaryTotal) }}</span></h4><a href="{{ route('beneficiaries.summary') }}" class="dashboard-stat-link">Ver informe</a></div><div class="dashboard-stat-icon dashboard-stat-icon-info flex-shrink-0"><i class="ri-group-line"></i></div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate dashboard-stat-card">
            <div class="card-body">
                <div class="d-flex align-items-start"><div class="flex-grow-1"><p class="dashboard-stat-title text-uppercase text-muted mb-0">Beneficiarios reportados</p></div><div class="flex-shrink-0"><span class="dashboard-stat-meta text-success"><i class="ri-arrow-right-up-line me-1"></i>Consolidados</span></div></div>
                <div class="d-flex align-items-end justify-content-between dashboard-stat-content"><div><h4 class="dashboard-stat-number ff-secondary"><span class="counter-value" data-target="{{ $reportedBeneficiaryCount }}">{{ number_format($reportedBeneficiaryCount) }}</span></h4><a href="{{ route('beneficiaries.summary', ['reported' => 1]) }}" class="dashboard-stat-link">Consultar reportados</a></div><div class="dashboard-stat-icon dashboard-stat-icon-success flex-shrink-0"><i class="ri-checkbox-circle-line"></i></div></div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card card-animate dashboard-stat-card">
            <div class="card-body">
                <div class="d-flex align-items-start"><div class="flex-grow-1"><p class="dashboard-stat-title text-uppercase text-muted mb-0">Pendientes por reportar</p></div><div class="flex-shrink-0"><span class="dashboard-stat-meta text-warning"><i class="ri-time-line me-1"></i>Pendientes</span></div></div>
                <div class="d-flex align-items-end justify-content-between dashboard-stat-content"><div><h4 class="dashboard-stat-number ff-secondary"><span class="counter-value" data-target="{{ $unreportedBeneficiaryCount }}">{{ number_format($unreportedBeneficiaryCount) }}</span></h4><a href="{{ route('beneficiaries.summary', ['reported' => 0]) }}" class="dashboard-stat-link">Revisar pendientes</a></div><div class="dashboard-stat-icon dashboard-stat-icon-warning flex-shrink-0"><i class="ri-time-line"></i></div></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-8">
        <div class="card dashboard-chart-card">
            <div class="card-header border-0 align-items-center d-flex">
                <div class="flex-grow-1"><h4 class="card-title mb-1">Actividad por proyecto e indicador</h4><p class="text-muted mb-0">Beneficiarios alcanzados en los indicadores con mayor actividad.</p></div>
                <span class="badge bg-primary-subtle text-primary">Top 15</span>
            </div>
            <div class="card-body p-0">
                @if($activityChart->isEmpty())
                    <div class="dashboard-empty"><i class="ri-bar-chart-grouped-line"></i><p>No hay datos para mostrar.</p></div>
                @else
                    <div id="activity-project-chart" class="apex-charts" dir="ltr"></div>
                @endif
            </div>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="card dashboard-chart-card">
            <div class="card-header border-0"><h4 class="card-title mb-1">Beneficiarios por lugar</h4><p class="text-muted mb-0">Distribuci&oacute;n entre los principales lugares de atenci&oacute;n.</p></div>
            <div class="card-body">
                @if($placeChart->isEmpty())
                    <div class="dashboard-empty"><i class="ri-map-pin-line"></i><p>No hay datos para mostrar.</p></div>
                @else
                    <div id="beneficiaries-place-chart" class="apex-charts" dir="ltr"></div>
                    <div class="dashboard-location-list mt-3">
                        @foreach($placeChart->take(5) as $place)
                            <div class="d-flex align-items-center justify-content-between py-2 border-bottom"><span class="text-truncate me-3"><i class="ri-map-pin-2-fill text-primary me-1"></i>{{ $place->place ?: 'Lugar sin especificar' }}</span><strong>{{ number_format($place->beneficiary_count) }}</strong></div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header align-items-center d-flex"><div class="flex-grow-1"><h4 class="card-title mb-1">Registros recientes</h4><p class="text-muted mb-0">&Uacute;ltimas actividades incorporadas al sistema.</p></div><a class="btn btn-soft-primary btn-sm" href="{{ route('reports.index') }}">Ver todos <i class="ri-arrow-right-line align-middle"></i></a></div>
            <div class="card-body p-0">
                @if($recentReports->isEmpty())
                    <div class="dashboard-empty"><i class="ri-file-add-line"></i><p>A&uacute;n no se han registrado actividades.</p><a class="btn btn-primary" href="{{ route('reports.create') }}">Crear el primer registro</a></div>
                @else
                    <div class="table-responsive"><table class="table table-hover table-nowrap align-middle mb-0">
                        <thead class="table-light"><tr><th>Fecha</th><th>Ubicaci&oacute;n</th><th>Proyecto / Indicador</th><th class="text-center">Beneficiarios</th><th>Estado</th><th></th></tr></thead>
                        <tbody>@foreach($recentReports as $report)
                            <tr>
                                <td><span class="fw-medium">{{ $report->report_date->format('d/m/Y') }}</span></td>
                                <td><strong>{{ $report->state?->name ?? 'Sin estado' }}</strong><br><small class="text-muted">{{ $report->municipality?->name ?? 'Sin municipio' }}</small></td>
                                <td><strong class="text-primary">{{ $report->proyecto?->codigo ?? $report->sector?->name ?? 'Sin proyecto' }}</strong><br><small class="text-muted dashboard-indicator-text">{{ \Illuminate\Support\Str::limit($report->indicadorProyecto?->indicador?->descripcion ?? $report->activity?->title ?? 'Sin indicador', 95) }}</small></td>
                                <td class="text-center"><span class="badge bg-info-subtle text-info fs-12">{{ number_format($report->total_beneficiaries) }}</span></td>
                                <td><span class="badge {{ $report->status === 'reviewed' ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning' }}">{{ $report->status === 'reviewed' ? 'Revisado' : 'Enviado' }}</span></td>
                                <td class="text-end"><a class="btn btn-soft-primary btn-sm" href="{{ route('reports.show', $report) }}" title="Ver registro"><i class="ri-eye-line"></i></a></td>
                            </tr>
                        @endforeach</tbody>
                    </table></div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.dashboard-stat-card{height:182px;min-height:182px;border:0;box-shadow:0 1px 2px rgba(56,65,74,.15)}.dashboard-stat-card .card-body{padding:20px}.dashboard-stat-title{font-size:13px;line-height:1.35;font-weight:600;letter-spacing:.025em;padding-right:10px}.dashboard-stat-meta{font-size:12px;line-height:1.35;font-weight:600;white-space:nowrap}.dashboard-stat-content{margin-top:27px}.dashboard-stat-number{margin:0 0 22px;font-size:25px;line-height:1;font-weight:600;color:#495057}.dashboard-stat-link{font-size:13px;text-decoration:underline;color:#405189}.dashboard-stat-icon{width:58px;height:58px;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:27px}.dashboard-stat-icon-primary{background:#e2e5f1;color:#405189}.dashboard-stat-icon-info{background:#dff3fb;color:#299cdb}.dashboard-stat-icon-success{background:#d9f3ee;color:#0ab39c}.dashboard-stat-icon-warning{background:#fef0d7;color:#f7b84b}.dashboard-chart-card{min-height:485px}.dashboard-chart-card .card-body{min-height:390px}.dashboard-empty{min-height:330px;display:flex;flex-direction:column;align-items:center;justify-content:center;color:#878a99;text-align:center;padding:30px}.dashboard-empty i{font-size:44px;color:#c6ccd2;margin-bottom:10px}.dashboard-location-list{max-height:205px;overflow:auto}.dashboard-indicator-text{display:inline-block;max-width:620px;white-space:normal}.apexcharts-tooltip{box-shadow:0 5px 10px rgba(30,32,37,.12)!important}@media(max-width:1199.98px){.dashboard-chart-card{min-height:auto}.dashboard-chart-card .card-body{min-height:350px}}@media(max-width:575.98px){.dashboard-stat-card{height:auto;min-height:172px}.dashboard-stat-title{font-size:12px}.dashboard-stat-content{margin-top:24px}}
</style>
@endpush

@push('scripts')
<script src="{{ asset('assets/libs/apexcharts/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const activityData = @json($activityChart);
    const activityElement = document.querySelector('#activity-project-chart');
    if (activityElement && typeof ApexCharts !== 'undefined') {
        new ApexCharts(activityElement, {
            series: [{name: 'Beneficiarios', data: activityData.map(item => Number(item.beneficiary_count))}],
            chart: {type: 'bar', height: 390, toolbar: {show: false}, fontFamily: 'inherit'},
            plotOptions: {bar: {horizontal: true, borderRadius: 4, barHeight: '58%', distributed: false}},
            dataLabels: {enabled: true, formatter: value => Number(value).toLocaleString('es-VE'), style: {fontSize: '11px'}},
            colors: ['#405189'],
            xaxis: {categories: activityData.map(item => `${item.sector} — ${item.activity}`), labels: {formatter: value => Number(value).toLocaleString('es-VE')}},
            yaxis: {labels: {maxWidth: 255, formatter: value => value.length > 42 ? `${value.slice(0, 39)}…` : value}},
            grid: {borderColor: '#e9ebec', strokeDashArray: 3},
            tooltip: {y: {formatter: value => `${Number(value).toLocaleString('es-VE')} beneficiarios`}},
        }).render();
    }

    const placeData = @json($placeChart);
    const placeElement = document.querySelector('#beneficiaries-place-chart');
    if (placeElement && typeof ApexCharts !== 'undefined') {
        new ApexCharts(placeElement, {
            series: placeData.map(item => Number(item.beneficiary_count)),
            labels: placeData.map(item => item.place || 'Lugar sin especificar'),
            chart: {type: 'donut', height: 260, fontFamily: 'inherit'},
            colors: ['#405189', '#0ab39c', '#f7b84b', '#299cdb', '#f06548', '#6559cc', '#3577f1', '#d7504b', '#45cb85', '#e9ebec', '#f672a7', '#6691e7'],
            legend: {show: false},
            dataLabels: {enabled: false},
            stroke: {width: 2, colors: ['#fff']},
            plotOptions: {pie: {donut: {size: '68%', labels: {show: true, total: {show: true, label: 'Beneficiarios', formatter: chart => chart.globals.seriesTotals.reduce((sum, value) => sum + value, 0).toLocaleString('es-VE')}}}}},
            tooltip: {y: {formatter: value => `${Number(value).toLocaleString('es-VE')} beneficiarios`}},
        }).render();
    }
});
</script>
@endpush
