@extends('layouts.app')

@section('title', 'Usuarios | Respuesta ASONACOP')

@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">Administraci&oacute;n</p>
        <h1>Usuarios del sistema</h1>
        <p class="muted">Cree y administre las cuentas encargadas de registrar las actividades.</p>
    </div>
    <a class="button button-primary" href="{{ route('users.create') }}">+ Nuevo usuario</a>
</section>

<section class="content-card">
    @if ($users->isEmpty())
        <div class="empty-state"><p>No hay usuarios registrados.</p></div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Ubicaciones asignadas</th><th>Registros</th><th>Beneficiarios</th><th>Creado</th><th></th></tr></thead>
            <tbody>
            @foreach ($users as $managedUser)
                <tr>
                    <td>{{ $managedUser->name }} @if ($managedUser->is(auth()->user()))<small>(usted)</small>@endif</td>
                    <td>{{ $managedUser->email }}</td>
                    <td><span class="role role-{{ $managedUser->role }}">{{ $roleLabels[$managedUser->role] ?? $managedUser->role }}</span></td>
                    <td><span class="status {{ $managedUser->is_active ? 'status-active' : 'status-inactive' }}">{{ $managedUser->is_active ? 'Activo' : 'Inactivo' }}</span></td>
                    <td>
                        @if ($managedUser->isAdministrator())
                            Todo el pa&iacute;s
                        @elseif ($managedUser->countrywide_access)
                            Todo el pa&iacute;s
                        @elseif ($managedUser->assignedStates->isEmpty() && $managedUser->assignedMunicipalities->isEmpty())
                            <span class="muted">Sin asignaci&oacute;n</span>
                        @else
                            {{ $managedUser->assignedStates->pluck('name')->join(', ') }}
                            @if ($managedUser->assignedStates->isNotEmpty() && $managedUser->assignedMunicipalities->isNotEmpty())<br>@endif
                            <small>{{ $managedUser->assignedMunicipalities->map(fn ($municipality) => $municipality->state->name.' / '.$municipality->name)->join(', ') }}</small>
                        @endif
                    </td>
                    <td>{{ number_format($managedUser->reports_count) }}</td>
                    <td>{{ number_format($managedUser->beneficiaries_count) }}</td>
                    <td>{{ $managedUser->created_at->format('d/m/Y') }}</td>
                    <td class="row-actions">
                        <a href="{{ route('users.edit', $managedUser) }}">Editar</a>
                        @if (! $managedUser->is(auth()->user()) && $managedUser->beneficiaries_count === 0)
                            <form action="{{ route('users.destroy', $managedUser) }}" method="post" onsubmit="return confirm('&iquest;Eliminar esta cuenta?');">
                                @csrf
                                @method('DELETE')
                                <button class="danger-link" type="submit">Eliminar</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        <div class="pagination">{{ $users->links() }}</div>
    @endif
</section>
@endsection
