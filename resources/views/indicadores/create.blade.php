@extends('layouts.app')
@section('title','Nuevo indicador | Respuesta ASONACOP')
@section('content')<section class="page-heading"><div><p class="eyebrow">Indicadores</p><h1>Registrar indicador</h1><p class="muted">Complete el c&oacute;digo, definici&oacute;n y clasificaci&oacute;n.</p></div></section><section class="content-card catalog-form-card"><form action="{{ route('indicadores.store') }}" method="post">@csrf @include('indicadores.form',['indicador'=>null,'submitLabel'=>'Guardar indicador'])</form></section>@endsection
