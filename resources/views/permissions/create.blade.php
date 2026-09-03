@extends('layouts.app')
@section('title', 'Nuevo permiso | Respuesta ASONACOP')
@section('content')
<section class="page-heading compact-heading">
    <div><p class="eyebrow">Seguridad</p><h1>Registrar permiso</h1><p class="muted">Cree una capacidad para asignarla posteriormente a uno o varios roles.</p></div>
</section>
<section class="content-card catalog-form-card">
    <form action="{{ route('permissions.store') }}" method="post">
        @csrf
        @include('permissions.form', ['permission' => null, 'protectedPermission' => false, 'submitLabel' => 'Guardar permiso'])
    </form>
</section>
@endsection
