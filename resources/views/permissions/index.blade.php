@extends('layouts.app')
@section('title', 'Permisos | Respuesta ASONACOP')
@section('content')
<section class="page-heading">
    <div><p class="eyebrow">Seguridad</p><h1>Permisos</h1><p class="muted">Administre las capacidades que pueden asignarse a los roles del sistema.</p></div>
    <a class="button button-primary" href="{{ route('permissions.create') }}">+ Nuevo permiso</a>
</section>
<section class="content-card">
    @if($permissions->isEmpty())
        <div class="empty-state"><p>No hay permisos registrados.</p></div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Permiso</th><th>Identificador</th><th>Roles asignados</th><th>Acciones</th></tr></thead>
                <tbody>
                @foreach($permissions as $permission)
                    <tr>
                        <td><strong>{{ str($permission->name)->headline() }}</strong></td>
                        <td><code>{{ $permission->name }}</code></td>
                        <td>{{ $permission->roles_count }}</td>
                        <td class="row-actions">
                            <a href="{{ route('permissions.edit', $permission) }}">Editar</a>
                            @if(!in_array($permission->name, ['administrar sistema', 'coordinar registros', 'registrar actividad', 'manejar lugares', 'editar registros', 'eliminar registros', 'solo ver registros'], true) && $permission->roles_count === 0)
                                <form action="{{ route('permissions.destroy', $permission) }}" method="post" onsubmit="return confirm('&iquest;Eliminar este permiso?');">
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
        <div class="pagination">{{ $permissions->links() }}</div>
    @endif
</section>
@endsection
