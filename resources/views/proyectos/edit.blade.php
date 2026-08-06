@extends('layouts.app')
@section('title','Editar proyecto | Respuesta ASONACOP')
@section('content')<section class="page-heading"><div><p class="eyebrow">Proyectos</p><h1>Editar proyecto</h1><p class="muted">Actualice {{ $proyecto->codigo }}.</p></div></section><section class="content-card catalog-form-card"><form action="{{ route('proyectos.update',$proyecto) }}" method="post">@csrf @method('PUT') @include('proyectos.form',['submitLabel'=>'Actualizar proyecto'])</form></section>@endsection
