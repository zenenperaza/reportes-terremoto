@extends('layouts.app')
@section('title','Sectores del proyecto | Respuesta ASONACOP')
@section('content')
<section class="page-heading project-indicator-heading">
    <div>
        <p class="eyebrow">Proyecto {{ $proyecto->codigo }}</p>
        <h1>Sectores del proyecto</h1>
        <p class="muted">{{ $proyecto->descripcion }} &middot; Donante: {{ $proyecto->donante->nombre }}</p>
    </div>
    <div class="heading-actions">
        <a class="button button-secondary" href="{{ route('proyectos.index') }}">&larr; Volver a proyectos</a>
        <button class="button button-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAgregarSectores" @disabled($sectoresDisponibles->isEmpty())>+ Agregar sectores</button>
    </div>
</section>

<section class="content-card">
    @if($asignaciones->isEmpty())
        <div class="empty-state">
            <p>Este proyecto todavía no tiene sectores asignados.</p>
            @if($sectoresDisponibles->isNotEmpty())
                <button class="button button-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAgregarSectores">Agregar el primer sector</button>
            @else
                <p class="muted">No hay sectores disponibles en el catálogo.</p>
            @endif
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Código</th><th>Sector</th><th>Indicadores</th><th>Acciones</th></tr></thead>
                <tbody>
                @foreach($asignaciones as $asignacion)
                    <tr>
                        <td><strong>{{ $asignacion->sector->codigo ?: 'SEC-'.$asignacion->sector->id }}</strong></td>
                        <td class="catalog-description">{{ $asignacion->sector->descripcion ?: $asignacion->sector->name }}</td>
                        <td><a class="indicator-count-link" href="{{ route('sector-proyecto.indicadores.index', $asignacion) }}">Gestionar ({{ $asignacion->asignaciones_indicadores_count }})</a></td>
                        <td class="row-actions">
                            <form action="{{ route('sector-proyecto.destroy', $asignacion) }}" method="post" onsubmit="return confirm('¿Desvincular este sector del proyecto? También se eliminarán sus indicadores asociados.');">
                                @csrf @method('DELETE')
                                <button class="danger-link" type="submit">Quitar</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $asignaciones->links() }}</div>
    @endif
</section>

<div class="modal fade" id="modalAgregarSectores" tabindex="-1" aria-labelledby="modalAgregarSectoresLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content catalog-modal">
            <form action="{{ route('proyectos.sectores.store', $proyecto) }}" method="post">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title" id="modalAgregarSectoresLabel">Agregar sectores</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label for="sector_ids">Sectores disponibles *</label>
                    <select name="sector_ids[]" id="sector_ids" class="js-sector-select" multiple required data-placeholder="Seleccione uno o varios sectores">
                        @foreach($sectoresDisponibles as $sector)
                            <option value="{{ $sector->id }}" @selected(in_array((string)$sector->id, array_map('strval', old('sector_ids', [])), true))>
                                {{ $sector->codigo ?: 'SEC-'.$sector->id }} — {{ $sector->descripcion ?: $sector->name }}
                            </option>
                        @endforeach
                    </select>
                    <small class="form-text">Puede buscar y seleccionar uno o varios sectores.</small>
                    @error('sector_ids')<p class="field-error">{{ $message }}</p>@enderror
                    @error('sector_ids.*')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div class="modal-footer">
                    <button class="button button-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="button button-primary" type="submit">Guardar sectores</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('sector_ids');
    const modal = document.getElementById('modalAgregarSectores');

    if (select && window.jQuery && jQuery.fn.select2) {
        jQuery(select).select2({
            width: '100%',
            placeholder: select.dataset.placeholder,
            dropdownParent: jQuery(modal)
        });
    }

    @if($errors->has('sector_ids') || $errors->has('sector_ids.*'))
        bootstrap.Modal.getOrCreateInstance(modal).show();
    @endif
});
</script>
@endpush
