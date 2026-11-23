@extends('layouts.app')
@section('title', 'Buzón | SIA')
@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.dataTables.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/datatables/buttons.dataTables.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/datatables/responsive.dataTables.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/buzon.css') }}?v={{ filemtime(public_path('css/buzon.css')) }}">
@endpush
@section('content')
@php($schema = \App\Models\BuzonEntry::class)
<section class="page-heading">
    <div><p class="eyebrow">Opiniones y encuestas</p><h1>Buzón</h1><p class="muted">{{ $schema::INDICATOR_CODE }} · {{ $schema::INDICATOR_NAME }}</p></div>
    <div class="buzon-entry-actions"><a class="button button-secondary" href="{{ route('buzon.lists') }}">Consultar listados</a>
        @can('crear buzon')<a class="button button-primary" href="{{ route('buzon.create', count($filters['project_ids']) === 1 ? ['proyecto_id' => $filters['project_ids'][0]] : []) }}">+ Registrar opinión</a>@endcan
    </div>
</section>
@include('buzon.partials.entries')
@endsection
@push('scripts')<script src="{{ asset('js/buzon.js') }}?v={{ filemtime(public_path('js/buzon.js')) }}"></script>@endpush
