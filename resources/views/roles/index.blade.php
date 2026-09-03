@extends('layouts.app')
@section('title', 'Roles | Respuesta ASONACOP')
@section('content')
<section class="page-heading">
    <div><p class="eyebrow">Seguridad</p><h1>Roles</h1><p class="muted">Defina las funciones del sistema y los permisos disponibles para cada una.</p></div>
    <a class="button button-primary" href="{{ route('roles.create') }}">+ Nuevo rol</a>
</section>

<section class="content-card">
    @if($roles->isEmpty())
        <div class="empty-state"><p>No hay roles registrados.</p></div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Rol</th><th>Permisos</th><th>Usuarios</th><th>Acciones</th></tr></thead>
                <tbody>
                @foreach($roles as $role)
                    <tr>
                        <td><strong>{{ \App\Models\User::ROLE_LABELS[$role->name] ?? str($role->name)->headline() }}</strong></td>
                        <td>
                            <div class="permission-tags">
                                @forelse($role->permissions as $permission)
                                    <span>{{ str($permission->name)->headline() }}</span>
                                @empty
                                    <span class="permission-tag-empty">Sin permisos</span>
                                @endforelse
                            </div>
                        </td>
                        <td>{{ $role->users_count }}</td>
                        <td class="row-actions">
                            <a href="{{ route('roles.edit', $role) }}">Editar</a>
                            @if(!in_array($role->name, ['admin', 'coordinator', 'reporter'], true) && $role->users_count === 0)
                                <form action="{{ route('roles.destroy', $role) }}" method="post" onsubmit="return confirm('&iquest;Eliminar este rol?');">
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
        <div class="pagination">{{ $roles->links() }}</div>
    @endif
</section>
@endsection
