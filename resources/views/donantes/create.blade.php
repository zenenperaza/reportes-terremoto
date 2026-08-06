@extends('layouts.app')
@section('title','Nuevo donante | Respuesta ASONACOP')
@section('content')
<section class="page-heading"><div><p class="eyebrow">Donantes</p><h1>Registrar donante</h1><p class="muted">Complete los datos de la organizaci&oacute;n donante.</p></div></section>
<section class="content-card catalog-form-card"><form action="{{ route('donantes.store') }}" method="post">@csrf @include('donantes.form',['donante'=>null,'submitLabel'=>'Guardar donante'])</form></section>
@endsection
