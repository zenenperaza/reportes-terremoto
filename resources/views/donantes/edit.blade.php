@extends('layouts.app')
@section('title','Editar donante | Respuesta ASONACOP')
@section('content')
<section class="page-heading"><div><p class="eyebrow">Donantes</p><h1>Editar donante</h1><p class="muted">Actualice los datos de {{ $donante->nombre }}.</p></div></section>
<section class="content-card catalog-form-card"><form action="{{ route('donantes.update',$donante) }}" method="post">@csrf @method('PUT') @include('donantes.form',['submitLabel'=>'Actualizar donante'])</form></section>
@endsection
