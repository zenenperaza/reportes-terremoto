@extends('layouts.app')
@section('title','Nuevo proyecto | Respuesta ASONACOP')
@section('content')<section class="page-heading"><div><p class="eyebrow">Proyectos</p><h1>Registrar proyecto</h1><p class="muted">Asocie el proyecto a un donante y defina su periodo.</p></div></section><section class="content-card catalog-form-card"><form action="{{ route('proyectos.store') }}" method="post">@csrf @include('proyectos.form',['proyecto'=>null,'submitLabel'=>'Guardar proyecto'])</form></section>@endsection
