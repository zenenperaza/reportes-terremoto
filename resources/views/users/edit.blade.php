@extends('layouts.app')

@section('title', 'Editar usuario | Respuesta UNICEF')

@section('content')
<section class="page-heading compact-heading">
    <div>
        <p class="eyebrow">Administración</p>
        <h1>Editar usuario</h1>
        <p class="muted">Actualice los datos o el nivel de acceso de {{ $managedUser->name }}.</p>
    </div>
    <a class="button button-secondary" href="{{ route('users.index') }}">Volver a usuarios</a>
</section>

<section class="content-card user-form-card">
    <form method="post" action="{{ route('users.update', $managedUser) }}" class="stack-form">
        @csrf
        @method('PUT')
        @include('users.form', ['submitLabel' => 'Guardar cambios'])
    </form>
</section>
@endsection
