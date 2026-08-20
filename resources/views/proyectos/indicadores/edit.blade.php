@extends('layouts.app')
@section('title','Editar indicador del proyecto | Respuesta ASONACOP')
@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">Proyecto {{ $indicadorProyecto->proyecto->codigo }}</p>
        <h1>Editar indicador del proyecto</h1>
        <p class="muted">Actualice las metas y el estado sin modificar el indicador maestro.</p>
    </div>
</section>
<section class="content-card catalog-form-card">
    <div class="selected-indicator-summary">
        <strong>{{ $indicadorProyecto->indicador->codigo }}</strong>
        <p>{{ $indicadorProyecto->indicador->descripcion }}</p>
    </div>
    <form action="{{ route('indicador-proyecto.update',$indicadorProyecto) }}" method="post">
        @csrf @method('PUT')
        <div class="form-grid two-cols indicator-single-meta">
            <label>Estado *
                <select name="estatus" required>
                    <option value="1" @selected((string)old('estatus',$indicadorProyecto->estatus)==='1')>Activo</option>
                    <option value="0" @selected((string)old('estatus',$indicadorProyecto->estatus)==='0')>Inactivo</option>
                </select>
            </label>
            <label>Meta cuantitativa
                <input type="number" name="meta_cuantitativa" value="{{ old('meta_cuantitativa',$indicadorProyecto->meta_cuantitativa) }}" min="0" step="1">
            </label>
            <label class="span-two">Meta cualitativa
                <textarea name="meta_cualitativa" rows="4" placeholder="Resultado cualitativo esperado">{{ old('meta_cualitativa',$indicadorProyecto->meta_cualitativa) }}</textarea>
            </label>
        </div>
        <div class="form-actions">
            <a class="button button-secondary" href="{{ $indicadorProyecto->sector_proyecto_id ? route('sector-proyecto.indicadores.index', $indicadorProyecto->sector_proyecto_id) : route('proyectos.indicadores.index', $indicadorProyecto->proyecto_id) }}">Cancelar</a>
            <button class="button button-primary" type="submit">Actualizar indicador</button>
        </div>
    </form>
</section>
@endsection
