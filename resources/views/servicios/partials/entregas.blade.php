<p class="muted programmed-service-responsive-help">En pantallas pequeñas, pulse + junto al beneficiario para ver los demás datos.</p>
<div class="table-wrap programmed-service-table-wrap">
    <table id="service-report-table" data-delivery-rows="1" class="programmed-services-table display responsive" style="width:100%">
        <thead><tr><th data-priority="1">Beneficiario</th><th>Edad / Sexo</th><th data-priority="2">{{ $filters['vista'] === 'beneficiario' ? 'Servicios entregados' : 'Servicio entregado' }}</th><th>Registro</th><th data-priority="3">Fecha de atención</th><th>Período</th><th>Proyecto</th><th>Indicador</th><th>Actividad</th><th>Sector</th><th data-priority="4">Acciones</th></tr></thead>
        <tbody>
        @foreach($entregas as $entrega)
            @php
                $person = $entrega['beneficiary'];
                $report = $person->report;
                $indicator = $report->indicadorProyecto;
                $activity = $report->actividadIndicador;
            @endphp
            <tr data-delivery-key="{{ $entrega['key'] }}">
                <td><strong>{{ $person->full_name ?: 'Sin nombre registrado' }}</strong><small>Beneficiario #{{ $person->id }}</small></td>
                <td>{{ $person->age }} años<small>{{ $person->sex }}</small></td>
                <td>@foreach($entrega['services'] as $service)<span class="report-service">{{ $service->servicio?->nombre ?? 'Sin descripción' }}</span>@if(!$loop->last)<br>@endif @endforeach</td>
                <td>#{{ $report->id }}</td>
                <td data-order="{{ $report->report_date->format('Y-m-d') }}">{{ $report->report_date->format('d/m/Y') }}</td>
                <td>{{ \App\Support\ReportPeriod::label($report->reporting_period) }}</td>
                <td>{{ $report->proyecto?->codigo ?? 'Sin proyecto' }}</td>
                <td>{{ $indicator?->indicador?->codigo ?? 'Sin indicador' }}</td>
                <td>{{ $activity?->actividad?->descripcion ?? 'Sin actividad' }}</td>
                <td>{{ $indicator?->asignacionSector?->sector?->name ?? 'Sin sector asignado' }}</td>
                <td><div class="programmed-service-row-actions">
                    <a href="{{ route('reports.show', $report) }}">Ver beneficiario</a>
                    @can('administrar sistema')
                        @foreach($entrega['services']->unique('actividad_indicador_id') as $service)
                            <a href="{{ route('actividad-indicador.servicios.index', $service->actividad_indicador_id) }}">Ver configuración</a>
                        @endforeach
                    @endcan
                </div></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
