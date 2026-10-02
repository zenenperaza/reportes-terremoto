@extends('layouts.app')
@section('title', 'Indicadores asociados | SIA')
@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">Proyecto {{ $asignacion->proyecto->codigo }}</p>
        <h1>Indicadores asociados</h1>
        <p><strong>{{ $asignacion->indicador->codigo }}</strong> — {{ $asignacion->indicador->descripcion }}</p>
    </div>
    <a class="button button-secondary" href="{{ $asignacion->sector_proyecto_id ? route('sector-proyecto.indicadores.index', $asignacion->sector_proyecto_id) : route('proyectos.indicadores.index', $asignacion->proyecto_id) }}">Volver a indicadores</a>
</section>
<section class="content-card">
    <p>Marque los indicadores del mismo proyecto que se ofrecerán al seleccionar este indicador principal. El registrador decidirá cuáles incluir.</p>
    <p class="muted">Cada asociado seleccionado generará un registro independiente con los mismos beneficiarios. No se incluyen asociados de los asociados. Los inactivos no se ofrecerán para nuevos registros.</p>
    <form method="post" action="{{ route('indicador-proyecto.asociados.update', $asignacion) }}">
        @csrf @method('PUT')
        @php($seleccionados = array_map('strval', old('asociados', session()->hasOldInput() ? [] : $asignacion->indicadoresAsociados->modelKeys())))
        <div class="table-wrap"><table>
            <thead><tr><th>Seleccionar</th><th>Código y descripción</th><th>Sector</th><th>Estado</th></tr></thead>
            <tbody>
                @forelse($opciones as $opcion)
                    <tr>
                        <td><input class="form-check-input" type="checkbox" name="asociados[]" value="{{ $opcion->id }}" id="asociado-{{ $opcion->id }}" @checked(in_array((string) $opcion->id, $seleccionados, true))></td>
                        <td><label for="asociado-{{ $opcion->id }}"><strong>{{ $opcion->indicador->codigo }}</strong><br>{{ $opcion->indicador->descripcion }}</label></td>
                        <td>{{ $opcion->asignacionSector?->sector?->name }}</td>
                        <td><span class="status {{ $opcion->estatus ? 'status-active' : 'status-inactive' }}">{{ $opcion->estatus ? 'Activo' : 'Inactivo' }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="4">Agregue otros indicadores al proyecto para poder asociarlos.</td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="form-actions"><button class="button button-primary" type="submit">Guardar asociados</button></div>
    </form>
</section>
@endsection
