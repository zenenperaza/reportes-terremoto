@php
    $indicator = $report->indicadorProyecto?->indicador;
    $projectActivity = $report->actividadIndicador?->actividad;
    $selectedServices = $report->serviciosActividad;
@endphp
<td class="report-classification"><strong>{{ $report->proyecto?->codigo ?? 'Sin proyecto asociado' }}</strong></td>
<td class="report-classification report-indicator">
    <strong>{{ $indicator?->codigo ?? $report->activity?->code ?? 'Sin indicador asociado' }}</strong>
    <br><small>{{ $indicator?->descripcion ?? $report->activity?->title ?? 'Sin descripción' }}</small>
</td>
<td class="report-classification">
    @if($projectActivity)<strong>{{ $projectActivity->codigo }}</strong><br><small>{{ $projectActivity->descripcion }}</small>
    @else<span class="muted">Sin actividad asociada</span>@endif
</td>
<td class="report-classification report-services">
    @forelse($selectedServices as $selectedService)
        <span class="report-service">{{ $selectedService->servicio?->nombre ?? 'Servicio sin descripción' }}</span>@if(!$loop->last)<br>@endif
    @empty<span class="muted">Sin servicios asociados</span>@endforelse
</td>
<td class="report-services-count" data-order="{{ $selectedServices->count() }}">{{ $selectedServices->count() }}</td>
