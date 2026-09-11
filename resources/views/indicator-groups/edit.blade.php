@extends('layouts.app')
@section('title', 'Editar grupo de indicadores | Respuesta ASONACOP')
@section('content')
<section class="page-heading compact-heading">
    <div><p class="eyebrow">Configuraci&oacute;n</p><h1>Editar grupo de indicadores</h1><p class="muted">Actualice {{ $indicatorGroup->name }}.</p></div>
</section>
<section class="content-card catalog-form-card">
    <form action="{{ route('indicator-groups.update', $indicatorGroup) }}" method="post">
        @csrf @method('PUT')
        @include('indicator-groups.form', ['submitLabel' => 'Guardar cambios'])
    </form>
</section>
@endsection
