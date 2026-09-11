@extends('layouts.app')
@section('title', 'Grupos de indicadores | Respuesta ASONACOP')
@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">Configuraci&oacute;n</p>
        <h1>Grupos de indicadores</h1>
        <p class="muted">Organice los indicadores en bloques tem&aacute;ticos y defina el orden en que se mostrar&aacute;n al registrar una actividad.</p>
    </div>
    <a class="button button-primary" href="{{ route('indicator-groups.create') }}">+ Nuevo grupo</a>
</section>

<section class="content-card">
    @if ($groups->isEmpty())
        <div class="empty-state"><p>No hay grupos de indicadores registrados.</p></div>
    @else
        <div class="table-wrap">
            <table>
                <thead><tr><th>Orden</th><th>Grupo</th><th>Descripci&oacute;n</th><th>Indicadores</th><th>Acciones</th></tr></thead>
                <tbody>
                @foreach ($groups as $group)
                    <tr>
                        <td><span class="catalog-tag">{{ $group->sort_order }}</span></td>
                        <td><strong>{{ $group->name }}</strong></td>
                        <td class="catalog-description">{{ $group->description ?: 'Sin descripci&oacute;n' }}</td>
                        <td>{{ $group->indicators_count }}</td>
                        <td class="row-actions">
                            <a href="{{ route('indicator-groups.edit', $group) }}">Editar</a>
                            @if ($group->indicators_count === 0)
                                <form action="{{ route('indicator-groups.destroy', $group) }}" method="post" onsubmit="return confirm('&iquest;Eliminar este grupo?');">
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
