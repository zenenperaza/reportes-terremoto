@extends('layouts.app')
@section('title', 'Nuevo caso — Buscar coincidencias | SIA')
@include('cases._assets')
@section('content')
<div class="case-module">
    <header class="case-heading"><div><span class="case-eyebrow">NUEVO EXPEDIENTE</span><h1>Buscar antes de crear</h1><p>Compruebe si la persona ya tiene un caso para evitar duplicados.</p></div><a class="btn btn-outline-secondary" href="{{ route('cases.index') }}">Volver a casos</a></header>
    <section class="case-surface case-start">
        <form method="get" action="{{ route('cases.start') }}">
            <div class="row g-3 align-items-end">
                <div class="col-md-4"><label class="form-label" for="search-by">Buscar por</label><select class="form-select" name="search_by" id="search-by">@foreach(['id' => 'Código o documento (exacto)', 'name' => 'Nombre', 'phone' => 'Teléfono (exacto)'] as $key => $label)<option value="{{ $key }}" @selected(($filters['search_by'] ?? 'id') === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-6"><label class="form-label" for="q">Datos de la persona</label><input class="form-control" name="q" id="q" value="{{ $filters['q'] ?? '' }}" maxlength="200" required autocomplete="off" placeholder="Escriba para buscar"></div>
                <div class="col-md-2"><button class="btn btn-primary w-100" type="submit"><i class="ri-search-line" aria-hidden="true"></i> Buscar</button></div>
            </div>
        </form>
        <p class="text-muted mt-3">La búsqueda consulta únicamente los expedientes a los que tiene acceso. La ausencia de resultados no descarta un caso restringido.</p>
        @if($matches !== null)
            <div class="case-search-results" role="status"><h2>{{ $matches->total() }} posibles coincidencias</h2>
                @forelse($matches as $match)
                    <article class="case-match"><div><strong>{{ $match->full_name }}</strong><small>{{ $match->reference }} · {{ $match->age_label }} · {{ $match->assignee?->name ?: 'Sin responsable' }}</small></div><a class="btn btn-outline-primary" href="{{ route('cases.show', $match) }}">Abrir caso</a></article>
                @empty<p>No se encontraron coincidencias accesibles con estos datos.</p>@endforelse
                {{ $matches->links() }}
            </div>
        @endif
        <div class="case-start-footer"><a class="btn btn-primary" href="{{ route('cases.create') }}"><i class="ri-add-line" aria-hidden="true"></i> {{ $matches === null ? 'Omitir y crear nuevo caso' : 'Continuar con un nuevo caso' }}</a></div>
    </section>
</div>
@endsection
