@extends('layouts.app')
@section('title', 'Editar permiso | Respuesta ASONACOP')
@section('content')
<section class="page-heading compact-heading">
    <div><p class="eyebrow">Seguridad</p><h1>Editar permiso</h1><p class="muted">Actualice {{ str($permission->name)->headline() }}.</p></div>
</section>
<section class="content-card catalog-form-card">
    <form action="{{ route('permissions.update', $permission) }}" method="post">
        @csrf @method('PUT')
        @include('permissions.form', ['submitLabel' => 'Guardar cambios'])
    </form>
</section>
@endsection
