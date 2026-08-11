@extends('layouts.app')
@section('title','Editar actividad | Respuesta ASONACOP')
@section('content')
<section class="page-heading"><div><p class="eyebrow">Actividades</p><h1>Editar actividad</h1><p class="muted">Actualice {{ $actividad->codigo }}.</p></div></section>
<section class="content-card catalog-form-card"><form action="{{ route('actividades.update',$actividad) }}" method="post">@csrf @method('PUT') @include('actividades.form',['submitLabel'=>'Actualizar actividad'])</form></section>
@endsection
