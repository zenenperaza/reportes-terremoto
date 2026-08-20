<article class="project-tree-indicator">
    <header>
        <div><span class="tree-level-label">Indicador</span><h3>{{ $indicadorProyecto->indicador->codigo }}</h3><p>{{ $indicadorProyecto->indicador->descripcion }}</p></div>
        <div class="tree-metas"><span>Meta cuantitativa: <strong>{{ $indicadorProyecto->meta_cuantitativa === null ? 'Sin meta' : number_format($indicadorProyecto->meta_cuantitativa) }}</strong></span><span>Meta cualitativa: <strong>{{ $indicadorProyecto->meta_cualitativa ?: 'Sin meta' }}</strong></span><span class="status {{ $indicadorProyecto->estatus ? 'status-active' : 'status-inactive' }}">{{ $indicadorProyecto->estatus ? 'Activo' : 'Inactivo' }}</span></div>
    </header>
    <div class="project-tree-activities">
        @forelse($indicadorProyecto->asignacionesActividades as $actividadIndicador)
            <section class="project-tree-activity">
                <div class="tree-activity-heading"><div><span class="tree-level-label">Actividad</span><h4>{{ $actividadIndicador->actividad->codigo }} &middot; {{ $actividadIndicador->actividad->descripcion }}</h4></div><div><span>Meta: <strong>{{ $actividadIndicador->meta === null ? 'Sin meta' : number_format($actividadIndicador->meta) }}</strong></span> <span class="status {{ $actividadIndicador->estatus ? 'status-active' : 'status-inactive' }}">{{ $actividadIndicador->estatus ? 'Activo' : 'Inactivo' }}</span></div></div>
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
