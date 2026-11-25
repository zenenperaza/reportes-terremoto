@extends('layouts.app')
@section('title', 'Listados del Buzón | SIA')
@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/datatables/dataTables.dataTables.min.css') }}">
<link rel="stylesheet" href="{{ asset('vendor/datatables/responsive.dataTables.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/buzon.css') }}?v={{ filemtime(public_path('css/buzon.css')) }}">
@endpush
@section('content')
<section class="page-heading"><div><p class="eyebrow">Buzón · Cargas de opiniones</p><h1>Listados del Buzón</h1>
    <p class="muted">Consulte y retome los listados creados durante la carga, incluso después de salir del sistema.</p></div>
    <div class="buzon-entry-actions"><a class="button button-secondary" href="{{ route('buzon.index') }}">Consultar opiniones</a>
        @can('crear buzon')<a class="button button-primary" href="{{ route('buzon.create') }}">+ Nuevo listado</a>@endcan</div>
</section>
<section class="content-card">
    <form method="get" action="{{ route('buzon.lists') }}" class="form-grid two-cols">
        <label class="span-two">Proyectos<select name="project_ids[]" id="buzon-project-filter" multiple data-placeholder="Todos los proyectos disponibles">
            @foreach($projects as $project)<option value="{{ $project->id }}" @selected(in_array((string) $project->id, array_map('strval', $filters['project_ids']), true))>{{ $project->codigo }} — {{ $project->nombre_alias ?: $project->descripcion }}</option>@endforeach
        </select></label>
        <div class="form-actions span-two"><a class="button button-secondary" href="{{ route('buzon.lists') }}">Limpiar filtros</a><button class="button button-primary" type="submit">Consultar listados</button></div>
    </form>
</section>
<section class="content-card"><h2>Listados guardados</h2>
    <p class="muted">Las opiniones anteriores a esta funcionalidad siguen en Consultar opiniones, sin agrupar. Los totales incluyen únicamente opiniones que puede consultar.</p>
    <div class="table-wrap buzon-lists-table-wrap"><table id="buzon-lists-table" class="buzon-table display" style="width:100%" data-url="{{ route('buzon.lists') }}" data-filters="{{ json_encode($filters) }}"><thead><tr><th>Listado</th><th>Creado</th><th>Proyecto</th><th>Creado por</th><th>Opiniones</th><th>Acciones</th></tr></thead><tbody>
    @foreach($lists as $listing)<tr>
        <td>#{{ $listing->id }}</td><td>{{ $listing->created_at->format('d/m/Y H:i') }}</td>
        <td><strong>{{ $listing->proyecto->codigo }}</strong><br>{{ $listing->proyecto->nombre_alias ?: $listing->proyecto->descripcion }}{{ $listing->proyecto->estatus ? '' : ' (Inactivo)' }}</td>
        <td>{{ $listing->creator?->name ?: 'Usuario no disponible' }}</td><td>{{ number_format($listing->entries_count) }}</td>
        <td>@include('buzon.lists.partials.actions')</td>
    </tr>@endforeach
    </tbody></table></div>
    <div data-buzon-lists-fallback>
        @if($lists->isEmpty())<p class="muted">No hay listados disponibles para los proyectos seleccionados.</p>@endif
        <div class="pagination">{{ $lists->links() }}</div>
    </div>
    <p id="buzon-lists-total" class="buzon-lists-total" role="status" aria-live="polite">Total de listados: <strong>{{ number_format($lists->total()) }}</strong></p>
</section>
@endsection
@push('scripts')
<script src="{{ asset('vendor/datatables/dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('js/buzon-lists-table.js') }}?v={{ filemtime(public_path('js/buzon-lists-table.js')) }}"></script>
<script src="{{ asset('js/buzon.js') }}?v={{ filemtime(public_path('js/buzon.js')) }}"></script>
@endpush
