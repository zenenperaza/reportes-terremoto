@extends('layouts.app')
@section('title','Indicadores del proyecto | Respuesta ASONACOP')
@section('content')
<section class="page-heading project-indicator-heading">
    <div>
        <p class="eyebrow">Proyecto {{ $proyecto->codigo }}</p>
        <h1>Indicadores del proyecto</h1>
        <p class="muted">{{ $proyecto->descripcion }} · Donante: {{ $proyecto->donante->nombre }}</p>
    </div>
    <div class="heading-actions">
        <a class="button button-secondary" href="{{ route('proyectos.index') }}">← Volver a proyectos</a>
        <button class="button button-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAgregarIndicador" @disabled($indicadoresDisponibles->isEmpty())>+ Agregar indicador</button>
    </div>
</section>

<section class="content-card">
@if($asignaciones->isEmpty())
    <div class="empty-state">
        <p>Este proyecto todavía no tiene indicadores asignados.</p>
        @if($indicadoresDisponibles->isNotEmpty())
            <button class="button button-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalAgregarIndicador">Agregar el primer indicador</button>
        @endif
    </div>
@else
    <div class="table-wrap"><table>
        <thead><tr><th>Código</th><th>Descripción</th><th>Meta cuantitativa</th><th>Meta cualitativa</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @foreach($asignaciones as $asignacion)
            <tr>
                <td><strong>{{ $asignacion->indicador->codigo }}</strong></td>
                <td class="catalog-description">{{ $asignacion->indicador->descripcion }}</td>
                <td>{{ $asignacion->meta_cuantitativa === null ? 'Sin meta' : number_format($asignacion->meta_cuantitativa) }}</td>
                <td class="catalog-description">{{ $asignacion->meta_cualitativa ?: 'Sin meta' }}</td>
                <td><span class="status {{ $asignacion->estatus ? 'status-active' : 'status-inactive' }}">{{ $asignacion->estatus ? 'Activo' : 'Inactivo' }}</span></td>
                <td class="row-actions">
                    <a href="{{ route('indicador-proyecto.edit',$asignacion) }}">Editar</a>
                    <form action="{{ route('indicador-proyecto.destroy',$asignacion) }}" method="post" onsubmit="return confirm('¿Desvincular este indicador del proyecto?');">
                        @csrf @method('DELETE')
                        <button class="danger-link" type="submit">Quitar</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="pagination">{{ $asignaciones->links() }}</div>
@endif
</section>

<div class="modal fade" id="modalAgregarIndicador" tabindex="-1" aria-labelledby="modalAgregarIndicadorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content catalog-modal">
            <form action="{{ route('proyectos.indicadores.store',$proyecto) }}" method="post">
                @csrf
                <div class="modal-header">
                    <h2 class="modal-title" id="modalAgregarIndicadorLabel">Agregar indicador</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <label>Indicador *
                        <select name="indicador_id" id="indicador_id" required>
                            <option value="">Seleccione un indicador</option>
                            @foreach($indicadoresDisponibles as $indicador)
                                <option value="{{ $indicador->id }}"
                                    data-code="{{ $indicador->codigo }}"
                                    data-description="{{ $indicador->descripcion }}"
                                    data-unit="{{ $indicador->unidad_conteo }}"
                                    data-space="{{ $indicador->espacio_coordinacion }}"
                                    data-population="{{ $indicador->poblacion_dirigida }}"
                                    @selected((string)old('indicador_id') === (string)$indicador->id)>
                                    {{ $indicador->codigo }} — {{ \Illuminate\Support\Str::limit($indicador->descripcion, 72) }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <div class="selected-indicator-preview" id="selected-indicator-preview" hidden>
                        <span class="preview-label">Indicador seleccionado</span>
                        <strong id="selected-indicator-code"></strong>
                        <p id="selected-indicator-description"></p>
                        <div class="indicator-preview-tags" id="selected-indicator-tags"></div>
                    </div>
                    <label>Meta cuantitativa
                        <input type="number" name="meta_cuantitativa" value="{{ old('meta_cuantitativa') }}" min="0" step="1" placeholder="0">
                    </label>
                    <label>Meta cualitativa
                        <textarea name="meta_cualitativa" rows="3" placeholder="Resultado cualitativo esperado">{{ old('meta_cualitativa') }}</textarea>
                    </label>
                </div>
                <div class="modal-footer">
                    <button class="button button-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="button button-primary" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    const select = document.getElementById('indicador_id');
    const preview = document.getElementById('selected-indicator-preview');
    const code = document.getElementById('selected-indicator-code');
    const description = document.getElementById('selected-indicator-description');
    const tags = document.getElementById('selected-indicator-tags');
    if (!select || !preview || !code || !description || !tags) return;

    function showDescription() {
        const option = select.options[select.selectedIndex];
        const text = option ? option.dataset.description : '';
        code.textContent = option ? option.dataset.code || '' : '';
        description.textContent = text || '';
        tags.replaceChildren();
        if (text) {
            [option.dataset.unit, option.dataset.space, option.dataset.population]
                .filter(Boolean)
                .forEach(function (value) {
                    const tag = document.createElement('span');
                    tag.textContent = value;
                    tags.appendChild(tag);
                });
        }
        preview.hidden = !text;
    }

    select.addEventListener('change', showDescription);
    showDescription();

    @if($errors->any())
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAgregarIndicador')).show();
    @endif
})();
</script>
@endpush
