@extends('layouts.app')

@section('title', 'Nombres del lugar | Respuesta ASONACOP')

@section('content')
<section class="page-heading compact-heading">
    <div>
        <p class="eyebrow">Catálogo compartido</p>
        <h1>Nombres específicos del lugar</h1>
        <p class="muted">Estos nombres estarán disponibles para todos los usuarios al crear un registro.</p>
    </div>
</section>

<section class="content-card">
    <h2>Crear nombre del lugar</h2>
    <form method="post" action="{{ route('place-names.store') }}" class="place-name-create-form">
        @csrf
        <label>Nombre específico del lugar *
            <input type="text" name="name" value="{{ old('name') }}" maxlength="200" required placeholder="Ej. Escuela Simón Bolívar">
        </label>
        <button class="button button-primary" type="submit">Crear lugar</button>
    </form>
</section>

<section class="content-card">
    <div class="card-heading"><div><h2>Lugares registrados</h2><p class="muted">Puede editar o retirar cualquier nombre del catálogo.</p></div></div>
    @if($placeNames->isEmpty())
        <div class="empty-state"><p>Aún no se han creado nombres de lugares.</p></div>
    @else
        <div class="table-wrap"><table>
            <thead><tr><th>Nombre</th><th>Creado por</th><th>Acciones</th></tr></thead>
            <tbody>@foreach($placeNames as $placeName)
                <tr>
                    <td>
                        <form method="post" action="{{ route('place-names.update', $placeName) }}" class="place-name-edit-form">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $placeName->name }}" maxlength="200" required aria-label="Nombre del lugar">
                            <button class="button button-secondary" type="submit">Guardar</button>
                        </form>
                    </td>
                    <td>{{ $placeName->creator?->name ?: '—' }}</td>
                    <td>
                        <form method="post" action="{{ route('place-names.destroy', $placeName) }}" onsubmit="return confirm('¿Eliminar este nombre del catálogo?');">
                            @csrf @method('DELETE')
                            <button class="danger-link" type="submit">Eliminar</button>
                        </form>
                    </td>
                </tr>
            @endforeach</tbody>
        </table></div>
        <div class="pagination">{{ $placeNames->links() }}</div>
    @endif
</section>
@endsection

@push('styles')
<style>
.place-name-create-form{display:flex;align-items:end;gap:12px;margin-top:18px}.place-name-create-form label{flex:1}.place-name-edit-form{display:flex;align-items:center;gap:8px}.place-name-edit-form input{min-width:280px}@media(max-width:560px){.place-name-create-form,.place-name-edit-form{align-items:stretch;flex-direction:column}.place-name-edit-form input{min-width:0}}
</style>
@endpush
