@extends('layouts.app')
@section('title', 'Listado #'.$listing->id.' | Buzón')
@push('styles')<link rel="stylesheet" href="{{ asset('css/buzon.css') }}?v={{ filemtime(public_path('css/buzon.css')) }}">@endpush
@section('content')
<section class="page-heading"><div><p class="eyebrow">Buzón · Listado #{{ $listing->id }}</p><h1>{{ $listing->proyecto->codigo }}</h1>
    <p class="muted">{{ $listing->proyecto->nombre_alias ?: $listing->proyecto->descripcion }} · Creado el {{ $listing->created_at->format('d/m/Y H:i') }}</p></div>
    <div class="buzon-entry-actions">
        @if($canAppendList)<a class="button button-primary" href="{{ route('buzon.create', ['list_id' => $listing->id]) }}">Agregar opiniones</a>@endif
        <a class="button button-secondary" href="{{ route('buzon.lists') }}">Volver a listados</a>
    </div>
</section>
@include('buzon.lists.partials.entries')
@endsection
@push('scripts')<script src="{{ asset('js/buzon.js') }}?v={{ filemtime(public_path('js/buzon.js')) }}"></script>@endpush
