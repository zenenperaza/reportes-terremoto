@php
    $labels345w = [
        'girls_0_5' => 'Niñas de 0 a 5 años',
        'boys_0_5' => 'Niños de 0 a 5 años',
        'girls_6_9' => 'Niñas de 6 a 9 años',
        'boys_6_9' => 'Niños de 6 a 9 años',
        'girls_10_11' => 'Niñas de 10 a 11 años',
        'boys_10_11' => 'Niños de 10 a 11 años',
        'boys_12_14' => 'Adolescentes masculinos de 12 a 14 años',
        'girls_12_14' => 'Adolescentes femeninas de 12 a 14 años',
        'boys_15_17' => 'Adolescentes masculinos de 15 a 17 años',
        'girls_15_17' => 'Adolescentes femeninas de 15 a 17 años',
        'women_18_59' => 'Mujeres de 18 a 59 años',
        'men_18_59' => 'Hombres de 18 a 59 años',
        'women_60_plus' => 'Mujeres de 60 años o más',
        'men_60_plus' => 'Hombres de 60 años o más',
    ];

    $consolidated345w = $summary345w['values'];
    $results345w = $summary345w['places'];
    $totalNna345w = $summary345w['total_nna'];
    $totalAdults345w = $summary345w['total_adults'];
    $total345w = $summary345w['total'];
@endphp

<section class="content-card results-345w-card">
    <div class="card-heading">
        <div>
            <h2>Resultado 345W</h2>
            <p class="muted">Personas atendidas en los espacios de alojamiento transitorio.</p>
        </div>
    </div>

    <div class="results-345w-totals" aria-label="Totales del resultado 345W">
        <div><span>NNA</span><strong>{{ number_format($totalNna345w) }}</strong></div>
        <div><span>Adultos</span><strong>{{ number_format($totalAdults345w) }}</strong></div>
        <div><span>Total de personas</span><strong>{{ number_format($total345w) }}</strong></div>
    </div>

    <div class="table-wrap results-345w-consolidated">
        <table class="summary-table">
            <thead><tr><th>Desagregado consolidado</th><th>Cantidad</th></tr></thead>
            <tbody>
                @foreach($labels345w as $index345w => $label345w)
                    <tr><td>{{ $label345w }}</td><td>{{ number_format($consolidated345w[$index345w]) }}</td></tr>
                    @if($loop->index === 9)
                        <tr class="summary-subtotal"><th>Total NNA</th><th>{{ number_format($totalNna345w) }}</th></tr>
                    @endif
                @endforeach
                <tr class="summary-subtotal"><th>Total de adultos</th><th>{{ number_format($totalAdults345w) }}</th></tr>
            </tbody>
            <tfoot><tr><th>Total de personas atendidas</th><th>{{ number_format($total345w) }}</th></tr></tfoot>
        </table>
    </div>
</section>

@if(empty($results345w))
    <section class="content-card empty-state">
        <p>No hay personas que coincidan con los filtros seleccionados.</p>
    </section>
@else
<div class="results-345w-places">
    @foreach($results345w as $place345w)
        <section class="content-card results-345w-place">
            <div class="card-heading">
                <div><h2>{{ $place345w['place'] }}</h2><p class="muted">Detalle por tipo de atención.</p></div>
                <strong class="beneficiary-number">{{ number_format($place345w['total']) }}</strong>
            </div>
            @foreach($place345w['services'] as $service345w)
                <div class="results-345w-service">
                    <h3>{{ $service345w['name'] }} <span>{{ number_format($service345w['total']) }}</span></h3>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Grupo de edad y sexo</th><th>Cantidad</th></tr></thead>
                            <tbody>
                                @foreach($labels345w as $index345w => $label345w)
                                    <tr><td>{{ $label345w }}</td><td>{{ number_format($service345w['values'][$index345w]) }}</td></tr>
                                @endforeach
                            </tbody>
                            <tfoot><tr><th>Total</th><th>{{ number_format($service345w['total']) }}</th></tr></tfoot>
                        </table>
                    </div>
                </div>
            @endforeach
        </section>
    @endforeach
</div>
@endif
