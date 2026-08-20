@extends('layouts.app')
@section('title', $proyecto->codigo.' | Respuesta ASONACOP')
@section('content')
<section class="page-heading project-detail-heading">
    <div><p class="eyebrow">Detalle del proyecto</p><h1>{{ $proyecto->codigo }}</h1><p class="muted">{{ $proyecto->descripcion }}</p></div>
    <div class="heading-actions"><a class="button button-secondary" href="{{ route('proyectos.index') }}">&larr; Volver a proyectos</a><a class="button button-primary" href="{{ route('proyectos.sectores.index', $proyecto) }}">Gestionar sectores</a></div>
</section>

<section class="content-card project-summary-card">
    <dl class="project-summary-grid">
        <div><dt>Donante</dt><dd>{{ $proyecto->donante->nombre }}</dd></div>
        <div><dt>Periodo</dt><dd>{{ $proyecto->inicio?->format('d/m/Y') ?? 'Sin fecha' }} &mdash; {{ $proyecto->fin?->format('d/m/Y') ?? 'Sin fecha' }}</dd></div>
        <div><dt>Estado</dt><dd><span class="status {{ $proyecto->estatus ? 'status-active' : 'status-inactive' }}">{{ $proyecto->estatus ? 'Activo' : 'Inactivo' }}</span></dd></div>
        <div><dt>Sectores</dt><dd>{{ $proyecto->asignacionesSectores->count() }}</dd></div>
        <div><dt>Indicadores</dt><dd>{{ $proyecto->asignacionesSectores->sum(fn ($sector) => $sector->asignacionesIndicadores->count()) }}</dd></div>
        <div><dt>Estados</dt><dd>{{ $proyecto->estados->pluck('name')->join(', ') ?: 'Sin estados asignados' }}</dd></div>
        <div><dt>Municipios</dt><dd>{{ $proyecto->municipios->isEmpty() ? 'Todos los municipios de los estados seleccionados' : $proyecto->municipios->map(fn($municipio) => $municipio->state->name.' / '.$municipio->name)->join(', ') }}</dd></div>
    </dl>
</section>

<section class="content-card project-tree-card">
    <div class="card-heading"><div><h2>Sectores del proyecto</h2><p class="muted">Indicadores · Actividades · Servicios configurados específicamente para cada sector.</p></div></div>
    @forelse($proyecto->asignacionesSectores as $sectorProyecto)
        <section class="project-tree-sector">
            <header class="project-tree-sector-heading">
                <div><span class="tree-level-label">Sector</span><h3>{{ $sectorProyecto->sector->codigo ?: 'SEC-'.$sectorProyecto->sector->id }}</h3><p>{{ $sectorProyecto->sector->descripcion ?: $sectorProyecto->sector->name }}</p></div>
                <a class="indicator-count-link" href="{{ route('sector-proyecto.indicadores.index', $sectorProyecto) }}">Gestionar indicadores ({{ $sectorProyecto->asignacionesIndicadores->count() }})</a>
            </header>
            <div class="project-tree-sector-content">
                @forelse($sectorProyecto->asignacionesIndicadores as $indicadorProyecto)
                    @include('proyectos._indicator_tree', ['indicadorProyecto' => $indicadorProyecto])
                @empty
                    <div class="empty-state"><p>Este sector todavía no tiene indicadores asignados.</p><a class="button button-primary" href="{{ route('sector-proyecto.indicadores.index', $sectorProyecto) }}">Agregar indicadores</a></div>
                @endforelse
            </div>
        </section>
    @empty
        <div class="empty-state"><p>Este proyecto todavía no tiene sectores asignados.</p><a class="button button-primary" href="{{ route('proyectos.sectores.index', $proyecto) }}">Agregar sectores</a></div>
    @endforelse

</section>
@endsection
