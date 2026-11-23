@extends('layouts.app')
@section('title', 'Buzón público | SIA')
@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.dataTables.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/datatables/buttons.dataTables.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/datatables/responsive.dataTables.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/buzon.css') }}?v={{ filemtime(public_path('css/buzon.css')) }}">
@endpush
@section('content')
@php($schema = \App\Models\BuzonEntry::class)
<section class="page-heading">
    <div><p class="eyebrow">Buzón · Enlaces externos</p><h1>Buzón público</h1>
        <p class="muted">Comparta enlaces y códigos QR por proyecto y consulte las opiniones recibidas mediante esos enlaces.</p></div>
    <div class="buzon-entry-actions">
        @can('crear buzon')<a class="button button-primary" href="#buzon-share-title"><i class="ri-qr-code-line" aria-hidden="true"></i> Generar QR y enlace</a>@endcan
        @can('ver buzon')<a class="button button-secondary" href="#buzon-opinions">Consultar opiniones públicas</a><a class="button button-secondary" href="{{ route('buzon.index') }}">Volver al Buzón</a>@endcan
    </div>
</section>
@include('buzon.partials.public-link')
@can('ver buzon')
    <p class="buzon-notice">Esta consulta incluye únicamente formularios enviados por enlaces externos. Los filtros, la búsqueda y las exportaciones PDF y Excel mantienen este alcance y los permisos de su usuario.</p>
    @include('buzon.partials.entries')
@else
    <p class="buzon-notice">Puede generar y compartir enlaces y QR. Para consultar opiniones anteriores necesita el permiso «ver buzon».</p>
@endcan
@endsection
@push('scripts')<script src="{{ asset('js/buzon.js') }}?v={{ filemtime(public_path('js/buzon.js')) }}"></script>@endpush
