@extends('layouts.app')
@section('title', $proyecto->codigo.' | Respuesta ASONACOP')
@section('content')
<section class="page-heading project-detail-heading">
    <div>
        <p class="eyebrow">Detalle del proyecto</p>
        <h1>{{ $proyecto->codigo }}</h1>
        <p class="muted">{{ $proyecto->descripcion }}</p>
    </div>
    <div class="heading-actions">
        <a class="button button-secondary" href="{{ route('proyectos.index') }}">← Volver a proyectos</a>
        <a class="button button-primary" href="{{ route('proyectos.indicadores.index',$proyecto) }}">Gestionar indicadores</a>
    </div>
</section>

<section class="content-card project-summary-card">
    <dl class="project-summary-grid">
        <div><dt>Donante</dt><dd>{{ $proyecto->donante->nombre }}</dd></div>
        <div><dt>Periodo</dt><dd>{{ $proyecto->inicio?->format('d/m/Y') ?? 'Sin fecha' }} — {{ $proyecto->fin?->format('d/m/Y') ?? 'Sin fecha' }}</dd></div>
        <div><dt>Estado</dt><dd><span class="status {{ $proyecto->estatus ? 'status-active' : 'status-inactive' }}">{{ $proyecto->estatus ? 'Activo' : 'Inactivo' }}</span></dd></div>
        <div><dt>Indicadores</dt><dd>{{ $proyecto->asignacionesIndicadores->count() }}</dd></div>
        <div><dt>Estados</dt><dd>{{ $proyecto->estados->pluck('name')->join(', ') ?: 'Sin estados asignados' }}</dd></div>
        <div><dt>Municipios</dt><dd>{{ $proyecto->municipios->isEmpty() ? 'Todos los municipios de los estados seleccionados' : $proyecto->municipios->map(fn($municipio) => $municipio->state->name.' / '.$municipio->name)->join(', ') }}</dd></div>
    </dl>
</section>

<section class="content-card project-tree-card">
    <div class="card-heading"><div><h2>Indicadores · Actividades · Servicios</h2><p class="muted">Estructura configurada específicamente para este proyecto.</p></div></div>
    @forelse($proyecto->asignacionesIndicadores as $indicadorProyecto)
        <article class="project-tree-indicator">
            <header>
                <div><span class="tree-level-label">Indicador</span><h3>{{ $indicadorProyecto->indicador->codigo }}</h3><p>{{ $indicadorProyecto->indicador->descripcion }}</p></div>
                <div class="tree-metas"><span>Meta cuantitativa: <strong>{{ $indicadorProyecto->meta_cuantitativa === null ? 'Sin meta' : number_format($indicadorProyecto->meta_cuantitativa) }}</strong></span><span>Meta cualitativa: <strong>{{ $indicadorProyecto->meta_cualitativa ?: 'Sin meta' }}</strong></span><span class="status {{ $indicadorProyecto->estatus ? 'status-active' : 'status-inactive' }}">{{ $indicadorProyecto->estatus ? 'Activo' : 'Inactivo' }}</span></div>
            </header>
            <div class="project-tree-activities">
                @forelse($indicadorProyecto->asignacionesActividades as $actividadIndicador)
                    <section class="project-tree-activity">
                        <div class="tree-activity-heading"><div><span class="tree-level-label">Actividad</span><h4>{{ $actividadIndicador->actividad->codigo }} · {{ $actividadIndicador->actividad->descripcion }}</h4></div><div><span>Meta: <strong>{{ $actividadIndicador->meta === null ? 'Sin meta' : number_format($actividadIndicador->meta) }}</strong></span> <span class="status {{ $actividadIndicador->estatus ? 'status-active' : 'status-inactive' }}">{{ $actividadIndicador->estatus ? 'Activo' : 'Inactivo' }}</span></div></div>
                        @if($actividadIndicador->asignacionesServicios->isEmpty())
                            <p class="tree-empty">Sin servicios asignados.</p>
                        @else
                            <ul class="project-tree-services">
                                @foreach($actividadIndicador->asignacionesServicios as $servicioActividad)
                                    <li><div><span class="tree-level-label">Servicio</span><strong>{{ $servicioActividad->servicio->nombre }}</strong>@if($servicioActividad->servicio->descripcion && $servicioActividad->servicio->descripcion !== $servicioActividad->servicio->nombre)<small>{{ $servicioActividad->servicio->descripcion }}</small>@endif</div><div class="tree-service-meta"><span>Cantidad: <strong>{{ $servicioActividad->cantidad_disponible === null ? 'Sin cantidad' : number_format($servicioActividad->cantidad_disponible) }}</strong></span><span class="status {{ $servicioActividad->estatus ? 'status-active' : 'status-inactive' }}">{{ $servicioActividad->estatus ? 'Activo' : 'Inactivo' }}</span></div></li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @empty
                    <p class="tree-empty">Este indicador no tiene actividades asignadas.</p>
                @endforelse
            </div>
        </article>
    @empty
        <div class="empty-state"><p>Este proyecto todavía no tiene indicadores asignados.</p><a class="button button-primary" href="{{ route('proyectos.indicadores.index',$proyecto) }}">Agregar indicadores</a></div>
    @endforelse
</section>
@endsection
