@extends('layouts.app')
@section('title','Familias')
@include('cases._assets')
@section('content')
<div class="case-module">
    <header class="case-heading"><div><span class="case-eyebrow">GESTIÓN DE CASOS</span><h1>Familias</h1><p>Grupos familiares e integrantes. Se muestran únicamente las familias autorizadas.</p></div>@can('create',App\Models\FamilyRecord::class)<a class="btn btn-primary" href="{{ route('families.create') }}">+ Nueva familia</a>@endcan</header>
    <form method="get" class="case-surface p-3 mb-4 d-flex gap-3"><label for="family-search" class="visually-hidden">Buscar familia</label><input id="family-search" name="q" class="form-control" value="{{ $filters['q'] ?? '' }}" placeholder="Nombre o código de familia"><button class="btn btn-primary">Buscar</button><a class="btn btn-outline-secondary" href="{{ route('families.index') }}">Limpiar</a></form>
    <div class="case-surface"><div class="table-responsive"><table class="table case-table"><thead><tr><th>Código</th><th>Familia</th><th>Fecha de registro</th><th>Responsable</th><th>Acciones</th></tr></thead><tbody>
        @forelse($families as $family)<tr><td><a href="{{ route('families.show',$family) }}">{{ $family->reference }}</a></td><td>{{ $family->name }}</td><td>{{ $family->registered_on->format('d/m/Y') }}</td><td>{{ $family->assignee?->name }}</td><td><a href="{{ route('families.show',$family) }}">Ver</a> @can('update',$family) · <a href="{{ route('families.edit',$family) }}">Editar</a>@endcan</td></tr>@empty<tr><td colspan="5" class="case-empty">No hay familias para mostrar.</td></tr>@endforelse
    </tbody></table></div><div class="p-3">{{ $families->links() }}</div></div>
</div>
@endsection
