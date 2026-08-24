@extends('layouts.app')
@section('title', 'Grupos de usuarios | Respuesta ASONACOP')
@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">Administraci&oacute;n</p>
        <h1>Grupos de usuarios</h1>
        <p class="muted">Organice registradores y coordinadores que pueden consultar los registros creados por los miembros de su mismo grupo.</p>
    </div>
    <a class="button button-primary" href="{{ route('user-groups.create') }}">+ Nuevo grupo</a>
</section>

<section class="content-card">
    @if ($groups->isEmpty())
        <div class="empty-state"><p>No hay grupos de usuarios registrados.</p></div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Grupo</th><th>Descripci&oacute;n</th><th>Miembros</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                @foreach ($groups as $group)
                    <tr>
                        <td><strong>{{ $group->name }}</strong></td>
                        <td>{{ $group->description ?: 'Sin descripci&oacute;n' }}</td>
                        <td>{{ $group->users_count }}</td>
                        <td><span class="catalog-tag">{{ $group->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                        <td class="row-actions">
                            <a href="{{ route('user-groups.edit', $group) }}">Editar</a>
                            @if ($group->users_count === 0)
                                <form action="{{ route('user-groups.destroy', $group) }}" method="post" onsubmit="return confirm('&iquest;Eliminar este grupo?');">
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
        <div class="pagination">{{ $groups->links() }}</div>
    @endif
</section>
@endsection
