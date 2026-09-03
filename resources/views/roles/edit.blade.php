@extends('layouts.app')
@section('title', 'Editar rol | Respuesta ASONACOP')
@section('content')
<section class="page-heading compact-heading">
    <div><p class="eyebrow">Seguridad</p><h1>Editar rol</h1><p class="muted">Actualice el rol {{ \App\Models\User::ROLE_LABELS[$role->name] ?? str($role->name)->headline() }}.</p></div>
</section>
<section class="content-card catalog-form-card">
    <form action="{{ route('roles.update', $role) }}" method="post">
        @csrf @method('PUT')
        @include('roles.form', ['submitLabel' => 'Guardar cambios'])
    </form>
</section>
@endsection
