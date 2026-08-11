@extends('layouts.app')
@section('title','Editar servicio | Respuesta ASONACOP')
@section('content')
<section class="page-heading"><div><p class="eyebrow">Servicios</p><h1>Editar servicio</h1><p class="muted">Actualice {{ $servicio->nombre }}.</p></div></section>
<section class="content-card catalog-form-card"><form action="{{ route('servicios.update',$servicio) }}" method="post">@csrf @method('PUT') @include('servicios.form',['submitLabel'=>'Actualizar servicio'])</form></section>
@endsection
