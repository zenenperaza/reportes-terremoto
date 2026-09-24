@extends('layouts.app')

@section('title', 'Informe por Indicadores | SIA')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/general-reports.css') }}?v={{ filemtime(public_path('css/general-reports.css')) }}">
@endpush

@section('content')
<section class="page-heading compact-heading general-report-heading">
    <div>
        <p class="eyebrow">Consolidado de respuesta</p>
        <h1>Informe por Indicadores</h1>
        <p class="muted">Analice la poblaci&oacute;n atendida mediante los mismos filtros y resumen base del consolidado general.</p>
    </div>
</section>

@include('general-reports.partials.filters-and-summary')

<section class="card general-chart-card indicator-groups-section">
    <div class="card-header">
        <div>
            <h2 class="card-title mb-1">Beneficiarios por indicadores</h2>
            <p class="text-muted mb-0">Revise el total de beneficiarios agrupado por grupo de indicadores y por cada indicador reportado.</p>
        </div>
    </div>
    <div class="card-body">
        @if (empty($indicatorGroupsSummary))
            <div class="general-empty indicator-groups-empty">
                <i class="ri-layout-grid-line"></i>
                <h2>Sin indicadores con resultados</h2>
                <p>Modifique los filtros para visualizar beneficiarios por indicador.</p>
            </div>
        @else
            <div class="indicator-card-grid">
                @foreach ($indicatorGroupsSummary as $group)
                    <section class="indicator-group-panel">
                        @php($groupName = trim((string) ($group['name'] ?? '')))
                        @php($groupLabel = preg_match('/^Atenci(?:o|ó)n\b/i', $groupName) ? $groupName : 'Atención '.$groupName)
                        <header class="indicator-group-header">
                            <strong>{{ $groupLabel }} ({{ $group['indicator_count'] }})</strong>
                            <div class="indicator-group-totals">
                                <span>Mujeres: {{ number_format($group['women'], 0, ',', '.') }}</span>
                                <span>Hombres: {{ number_format($group['men'], 0, ',', '.') }}</span>
                                <span>Total: {{ number_format($group['beneficiaries'], 0, ',', '.') }}</span>
                            </div>
                        </header>
                        <p class="indicator-group-description">{{ $group['description'] ?: 'Sin descripcion disponible.' }} Total beneficiarios: {{ number_format($group['beneficiaries'], 0, ',', '.') }}.</p>

                        <div class="indicator-group-items">
                            @foreach ($group['items'] as $indicator)
                                <article class="indicator-card indicator-summary-card">
                                    <span class="indicator-card-top">
                                        @php($coordination = \Illuminate\Support\Str::upper((string) ($indicator['coordination_space'] ?? '')))
                                        @php($coordinationClass = $coordination === 'VBG' ? 'is-vbg' : ($coordination === 'NNA/VBG' ? 'is-mixed' : 'is-nna'))
                                        <span class="indicator-coordination {{ $coordinationClass }}">{{ $indicator['coordination_space'] ?: 'NNA' }}</span>
                                        <strong>{{ $indicator['code'] }}</strong>
                                        <span class="indicator-summary-total">{{ number_format($indicator['beneficiaries'], 0, ',', '.') }}</span>
                                    </span>
                                    <span class="indicator-card-description">{{ $indicator['title'] }}</span>
                                    <span class="indicator-card-meta">{{ $indicator['unit'] ?: 'Personas' }} &middot; Edad: {{ $indicator['age_from'] }} a {{ $indicator['age_to'] }} a&ntilde;os &middot; H: {{ $indicator['men'] }} &middot; M: {{ $indicator['women'] }}</span>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</section>

@endsection

@push('scripts')
<script src="{{ asset('js/general-report-locations.js') }}?v={{ filemtime(public_path('js/general-report-locations.js')) }}"></script>
@include('general-reports.partials.filter-scripts')
@endpush

