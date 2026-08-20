@extends('layouts.app')
@section('title','Sectores | Respuesta ASONACOP')
@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">Configuración</p>
        <h1>Sectores</h1>
        <p class="muted">Administre el catálogo maestro de sectores programáticos.</p>
    </div>
    <a class="button button-primary" href="{{ route('sectores.create') }}">+ Nuevo sector</a>
</section>

<section class="content-card">
    @if($sectores->isEmpty())
        <div class="empty-state">
            <p>No hay sectores registrados.</p>
            <a class="button button-primary" href="{{ route('sectores.create') }}">Registrar el primer sector</a>
        </div>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Código</th><th>Descripción</th><th>Proyectos asignados</th><th>Estado</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                @foreach($sectores as $sector)
                    <tr>
                        <td><strong>{{ $sector->codigo ?: 'SEC-'.$sector->id }}</strong></td>
                        <td class="catalog-description">{{ $sector->descripcion ?: $sector->name }}</td>
                        <td>{{ $sector->proyectos_count }}</td>
                        <td><span class="catalog-tag">{{ $sector->estatus ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="row-actions">
                            <a href="{{ route('sectores.edit', $sector) }}">Editar</a>
                            <form action="{{ route('sectores.toggle-status', $sector) }}" method="post">
                                @csrf @method('PATCH')
                                <button class="table-action" type="submit">{{ $sector->estatus ? 'Desactivar' : 'Activar' }}</button>
                            </form>
                            @if($sector->proyectos_count === 0 && $sector->activities_count === 0)
                                <form action="{{ route('sectores.destroy', $sector) }}" method="post" onsubmit="return confirm('¿Eliminar este sector?');">
                                    @csrf @method('DELETE')
                                    <button class="danger-link" type="submit">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $sectores->links() }}</div>
    @endif
</section>
@endsection
