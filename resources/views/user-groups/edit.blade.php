@extends('layouts.app')
@section('title', 'Editar grupo de usuarios | Respuesta ASONACOP')
@section('content')
<section class="page-heading compact-heading">
    <div><p class="eyebrow">Administraci&oacute;n</p><h1>Editar grupo de usuarios</h1><p class="muted">Actualice {{ $userGroup->name }}.</p></div>
</section>
<section class="content-card catalog-form-card">
    <form action="{{ route('user-groups.update', $userGroup) }}" method="post">
        @csrf @method('PUT')
        @include('user-groups.form', ['submitLabel' => 'Guardar cambios'])
    </form>
</section>
@endsection
