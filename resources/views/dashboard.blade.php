@extends('layouts.app')

@section('title', 'Panel | Respuesta ASONACOP')

@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">{{ $isCoordinator ? 'Vista de coordinación' : 'Mi espacio de registros' }}</p>
        <h1>{{ $isCoordinator ? 'Seguimiento de la respuesta' : 'Mis actividades registradas' }}</h1>
        <p class="muted">{{ $isCoordinator ? 'Consolide los registros recibidos y revise sus evidencias.' : 'Cada registro conserva sus datos, desagregación y soportes.' }}</p>
    </div>
    <a class="button button-primary" href="{{ route('reports.create') }}">Registrar actividad</a>
</section>

<section class="stats-grid" aria-label="Resumen">
    <article class="stat-card"><span>Registros</span><strong>{{ number_format($reportCount) }}</strong></article>
    <article class="stat-card"><span>Beneficiarios alcanzados</span><strong>{{ number_format($beneficiaryTotal) }}</strong></article>
    <article class="stat-card"><span>Beneficiarios reportados</span><strong>{{ number_format($reportedBeneficiaryCount) }}</strong></article>
    <article class="stat-card"><span>Beneficiarios no reportados</span><strong>{{ number_format($unreportedBeneficiaryCount) }}</strong></article>
</section>

<section class="dashboard-charts" aria-label="Gráficos de beneficiarios">
    <article class="content-card chart-card">
        <div class="card-heading"><div><h2>Actividad a reportar por Sector</h2><p class="muted">Beneficiarios agrupados por sector y actividad.</p></div></div>
        @if($activityChart->isEmpty())
            <div class="empty-state"><p>No hay datos para mostrar.</p></div>
        @else
            <div class="chart-canvas-wrap"><canvas id="activity-sector-chart" aria-label="Beneficiarios por actividad y sector" role="img"></canvas></div>
        @endif
    </article>
    <article class="content-card chart-card">
        <div class="card-heading"><div><h2>Nombre específico del lugar</h2><p class="muted">Beneficiarios alcanzados por lugar.</p></div></div>
        @if($placeChart->isEmpty())
            <div class="empty-state"><p>No hay datos para mostrar.</p></div>
        @else
            <div class="chart-canvas-wrap"><canvas id="place-chart" aria-label="Beneficiarios por nombre específico del lugar" role="img"></canvas></div>
        @endif
    </article>
</section>

<section class="content-card">
    <div class="card-heading"><h2>Registros recientes</h2><a href="{{ route('reports.index') }}">Ver todos</a></div>
    @if ($recentReports->isEmpty())
        <div class="empty-state"><p>Aún no se han registrado actividades.</p><a class="button button-primary" href="{{ route('reports.create') }}">Crear el primer registro</a></div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Fecha</th><th>Ubicación</th><th>Sector</th><th>Beneficiarios</th><th>Estado</th><th></th></tr></thead>
            <tbody>@foreach ($recentReports as $report)
                <tr>
                    <td>{{ $report->report_date->format('d/m/Y') }}</td>
                    <td>{{ $report->state->name }} · {{ $report->municipality->name }}</td>
                    <td>{{ $report->sector->name }}</td>
                    <td>{{ number_format($report->total_beneficiaries) }}</td>
                    <td><span class="status status-{{ $report->status }}">{{ $report->status === 'reviewed' ? 'Revisado' : 'Enviado' }}</span></td>
                    <td><a href="{{ route('reports.show', $report) }}">Abrir</a></td>
                </tr>
            @endforeach</tbody>
        </table></div>
    @endif
</section>
@endsection

@push('styles')
<style>
.dashboard-charts{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}.chart-card{min-width:0}.chart-canvas-wrap{position:relative;height:430px}@media(max-width:900px){.dashboard-charts{grid-template-columns:1fr}}@media(max-width:560px){.chart-canvas-wrap{height:360px}}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
<script>
const dashboardIntegerTicks = {beginAtZero: true, ticks: {precision: 0, color: '#617484'}, grid: {color: '#e5edf1'}};
const dashboardChartOptions = {
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {display: false},
        tooltip: {callbacks: {label: context => ` ${Number(context.raw).toLocaleString('es-VE')} beneficiarios`}},
    },
    scales: {
        x: dashboardIntegerTicks,
        y: {
            ticks: {
                color: '#183447',
                autoSkip: false,
                font: {size: 11},
                callback(value) {
                    const label = this.getLabelForValue(value);
                    return label.length > 48 ? `${label.slice(0, 45)}…` : label;
                },
            },
            grid: {display: false},
        },
    },
};
const activityChartElement = document.getElementById('activity-sector-chart');
if (activityChartElement && typeof Chart !== 'undefined') {
    const activityData = @json($activityChart);
    new Chart(activityChartElement, {
        type: 'bar',
        data: {
            labels: activityData.map(item => `${item.sector} — ${item.activity}`),
            datasets: [{data: activityData.map(item => item.beneficiary_count), backgroundColor: '#1cabe2', borderRadius: 5}],
        },
        options: dashboardChartOptions,
    });
}
const placeChartElement = document.getElementById('place-chart');
if (placeChartElement && typeof Chart !== 'undefined') {
    const placeData = @json($placeChart);
    new Chart(placeChartElement, {
        type: 'bar',
        data: {
            labels: placeData.map(item => item.place),
            datasets: [{data: placeData.map(item => item.beneficiary_count), backgroundColor: '#197a59', borderRadius: 5}],
        },
        options: dashboardChartOptions,
    });
}
</script>
@endpush
