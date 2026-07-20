@extends('layouts.app')

@section('title', 'Nuevo usuario | Respuesta ASONACOP')

@section('content')
<section class="page-heading compact-heading">
    <div>
        <p class="eyebrow">Administración</p>
        <h1>Registrar usuario</h1>
        <p class="muted">Esta cuenta podrá ingresar y registrar sus actividades.</p>
    </div>
</section>

<section class="content-card user-form-card">
    <form method="post" action="{{ route('users.store') }}" class="stack-form">
        @csrf
        @include('users.form', ['managedUser' => null, 'submitLabel' => 'Crear usuario'])
    </form>
</section>
@endsection
