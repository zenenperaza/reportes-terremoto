@extends('layouts.app')
@section('title', 'Nuevo grupo de usuarios | Respuesta ASONACOP')
@section('content')
<section class="page-heading compact-heading">
    <div><p class="eyebrow">Administraci&oacute;n</p><h1>Registrar grupo de usuarios</h1><p class="muted">Cree un equipo para compartir la consulta de registros entre sus miembros.</p></div>
</section>
<section class="content-card catalog-form-card">
    <form action="{{ route('user-groups.store') }}" method="post">
        @csrf
        @include('user-groups.form', ['userGroup' => null, 'submitLabel' => 'Guardar grupo'])
    </form>
</section>
@endsection
