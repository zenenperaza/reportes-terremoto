@extends('layouts.app')
@section('title', 'Nuevo grupo de indicadores | Respuesta ASONACOP')
@section('content')
<section class="page-heading compact-heading">
    <div><p class="eyebrow">Configuraci&oacute;n</p><h1>Registrar grupo de indicadores</h1><p class="muted">Cree un bloque tem&aacute;tico para organizar la selecci&oacute;n de indicadores.</p></div>
</section>
<section class="content-card catalog-form-card">
    <form action="{{ route('indicator-groups.store') }}" method="post">
        @csrf
        @include('indicator-groups.form', ['indicatorGroup' => null, 'submitLabel' => 'Guardar grupo'])
    </form>
</section>
@endsection
