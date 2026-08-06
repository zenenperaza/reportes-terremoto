@extends('layouts.app')
@section('title','Editar indicador | Respuesta ASONACOP')
@section('content')<section class="page-heading"><div><p class="eyebrow">Indicadores</p><h1>Editar indicador</h1><p class="muted">Actualice {{ $indicador->codigo }}.</p></div></section><section class="content-card catalog-form-card"><form action="{{ route('indicadores.update',$indicador) }}" method="post">@csrf @method('PUT') @include('indicadores.form',['submitLabel'=>'Actualizar indicador'])</form></section>@endsection
