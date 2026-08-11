@extends('layouts.app')
@section('title','Nuevo servicio | Respuesta ASONACOP')
@section('content')
<section class="page-heading"><div><p class="eyebrow">Servicios</p><h1>Registrar servicio</h1><p class="muted">Cree un servicio para asignarlo posteriormente a las actividades.</p></div></section>
<section class="content-card catalog-form-card"><form action="{{ route('servicios.store') }}" method="post">@csrf @include('servicios.form',['servicio'=>null,'submitLabel'=>'Guardar servicio'])</form></section>
@endsection
