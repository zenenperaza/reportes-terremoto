@extends('layouts.app')
@section('title','Actividades del indicador | Respuesta ASONACOP')
@section('content')
<section class="page-heading project-indicator-heading">
    <div>
        <p class="eyebrow">Proyecto {{ $indicadorProyecto->proyecto->codigo }}</p>
        <h1>Actividades del indicador</h1>
        <p class="muted"><strong>{{ $indicadorProyecto->indicador->codigo }}</strong> · {{ $indicadorProyecto->indicador->descripcion }}</p>
    </div>
    <div class="heading-actions">
        <a class="button button-secondary" href="{{ route('proyectos.indicadores.index',$indicadorProyecto->proyecto) }}">← Volver a indicadores</a>
        <button class="button button-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAgregarActividad" @disabled($actividadesDisponibles->isEmpty())>+ Agregar actividad</button>
    </div>
</section>

<section class="content-card">
    @if($asignaciones->isEmpty())
        <div class="empty-state"><p>Este indicador todavía no tiene actividades asignadas.</p></div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Código</th><th>Actividad</th><th>Meta</th><th>Estado</th><th>Servicios</th><th></th></tr></thead>
            <tbody>
            @foreach($asignaciones as $asignacion)
                <tr>
                    <td><strong>{{ $asignacion->actividad->codigo }}</strong></td>
                    <td class="catalog-description">{{ $asignacion->actividad->descripcion }}</td>
                    <td>
                        <form class="assignment-inline-form" action="{{ route('actividad-indicador.update',$asignacion) }}" method="post">
                            @csrf @method('PUT')
                            <input type="number" name="meta" value="{{ $asignacion->meta }}" min="0" placeholder="Sin meta" aria-label="Meta">
                            <select name="estatus"><option value="1" @selected($asignacion->estatus)>Activo</option><option value="0" @selected(!$asignacion->estatus)>Inactivo</option></select>
                            <button class="button button-secondary button-small" type="submit">Guardar</button>
                        </form>
                    </td>
                    <td><a class="indicator-count-link" href="{{ route('actividad-indicador.servicios.index',$asignacion) }}">Gestionar servicios</a></td>
                    <td class="row-actions"><form action="{{ route('actividad-indicador.destroy',$asignacion) }}" method="post" onsubmit="return confirm('¿Quitar esta actividad y sus servicios?');">@csrf @method('DELETE')<button class="danger-link" type="submit">Quitar</button></form></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="pagination">{{ $asignaciones->links() }}</div>
    @endif
</section>

<div class="modal fade" id="modalAgregarActividad" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content catalog-modal">
        <form action="{{ route('indicador-proyecto.actividades.store',$indicadorProyecto) }}" method="post">@csrf
            <div class="modal-header"><h2 class="modal-title">Agregar actividad</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <label>Actividad *<select name="actividad_id" required><option value="">Seleccione una actividad</option>@foreach($actividadesDisponibles as $actividad)<option value="{{ $actividad->id }}" @selected(old('actividad_id')==$actividad->id)>{{ $actividad->codigo }} — {{ $actividad->descripcion }}</option>@endforeach</select></label>
                <label>Meta<input type="number" name="meta" value="{{ old('meta') }}" min="0" placeholder="0"></label>
            </div>
            <div class="modal-footer"><button class="button button-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="button button-primary" type="submit">Guardar</button></div>
        </form>
    </div></div>
</div>
@endsection
@push('scripts')
@if($errors->any())<script>bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAgregarActividad')).show();</script>@endif
@endpush
