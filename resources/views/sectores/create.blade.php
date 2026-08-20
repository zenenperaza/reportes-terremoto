@extends('layouts.app')
@section('title','Nuevo sector | Respuesta ASONACOP')
@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">Sectores</p>
        <h1>Registrar sector</h1>
        <p class="muted">Cree un sector para asignarlo posteriormente a uno o varios proyectos.</p>
    </div>
</section>
<section class="content-card catalog-form-card">
    <form action="{{ route('sectores.store') }}" method="post">
        @csrf
        @include('sectores.form', ['sector' => null, 'submitLabel' => 'Guardar sector'])
    </form>
</section>
@endsection
