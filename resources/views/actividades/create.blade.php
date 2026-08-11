@extends('layouts.app')
@section('title','Nueva actividad | Respuesta ASONACOP')
@section('content')
<section class="page-heading"><div><p class="eyebrow">Actividades</p><h1>Registrar actividad</h1><p class="muted">Cree una actividad para asignarla posteriormente a los indicadores de cada proyecto.</p></div></section>
<section class="content-card catalog-form-card"><form action="{{ route('actividades.store') }}" method="post">@csrf @include('actividades.form',['actividad'=>null,'submitLabel'=>'Guardar actividad'])</form></section>
@endsection
