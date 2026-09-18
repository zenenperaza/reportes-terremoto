@extends('layouts.app')
@section('title','Editar sector | SIA')
@section('content')
<section class="page-heading">
    <div>
        <p class="eyebrow">Sectores</p>
        <h1>Editar sector</h1>
        <p class="muted">Actualice {{ $sector->codigo ?: $sector->name }}.</p>
    </div>
</section>
<section class="content-card catalog-form-card">
    <form action="{{ route('sectores.update', $sector) }}" method="post">
        @csrf @method('PUT')
        @include('sectores.form', ['submitLabel' => 'Actualizar sector'])
    </form>
</section>
@endsection
