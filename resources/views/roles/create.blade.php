@extends('layouts.app')
@section('title', 'Nuevo rol | Respuesta ASONACOP')
@section('content')
<section class="page-heading compact-heading">
    <div><p class="eyebrow">Seguridad</p><h1>Registrar rol</h1><p class="muted">Cree un rol y seleccione las capacidades que tendrá.</p></div>
</section>
<section class="content-card catalog-form-card">
    <form action="{{ route('roles.store') }}" method="post">
        @csrf
        @include('roles.form', ['role' => null, 'protectedRole' => false, 'submitLabel' => 'Guardar rol'])
    </form>
</section>
@endsection
