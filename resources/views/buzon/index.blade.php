@extends('layouts.app')
@section('title', 'Buzón | SIA')
@push('styles')<link rel="stylesheet" href="{{ asset('css/buzon.css') }}?v={{ filemtime(public_path('css/buzon.css')) }}">@endpush
@section('content')
@php($schema = \App\Models\BuzonEntry::class)
<section class="page-heading">
    <div><p class="eyebrow">Opiniones y encuestas</p><h1>Buzón</h1><p class="muted">{{ $schema::INDICATOR_CODE }} · {{ $schema::INDICATOR_NAME }}</p></div>
    <a class="button button-primary" href="{{ route('buzon.create', count($filters['project_ids']) === 1 ? ['proyecto_id' => $filters['project_ids'][0]] : []) }}">+ Registrar opinión</a>
</section>
<section class="content-card">
    <h2>Consultar opiniones</h2>
    <form method="get" action="{{ route('buzon.index') }}" class="form-grid two-cols" data-buzon-filters>
        <label class="span-two">Proyectos
            <select name="project_ids[]" id="buzon-project-filter" multiple data-placeholder="Todos los proyectos disponibles">
                @foreach($projects as $project)<option value="{{ $project->id }}" @selected(in_array((string) $project->id, array_map('strval', $filters['project_ids']), true))>{{ $project->codigo }} — {{ $project->nombre_alias ?: $project->descripcion }}{{ $project->estatus ? '' : ' (Inactivo)' }}</option>@endforeach
            </select><small>Seleccione uno o varios. Sin selección se consultan todos sus proyectos disponibles.</small>
        </label>
        <label>Fecha de la opinión desde<input type="date" name="from" id="buzon-from" value="{{ $filters['from'] }}"></label>
        <label>Fecha de la opinión hasta<input type="date" name="to" id="buzon-to" value="{{ $filters['to'] }}"></label>
        <label>Tipo de opinión<select name="opinion"><option value="">Todas</option>@foreach($schema::OPINIONS as $value => $label)<option value="{{ $value }}" @selected($filters['opinion'] === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>Tipo de retroalimentación<select name="feedback_type"><option value="">Todos</option>@foreach($schema::FEEDBACK_TYPES as $value => $label)<option value="{{ $value }}" @selected($filters['feedback_type'] === $value)>{{ $label }}</option>@endforeach</select></label>
        <div class="form-actions span-two"><a class="button button-secondary" href="{{ route('buzon.index') }}">Limpiar filtros</a><button class="button button-primary" type="submit">Consultar Buzón</button></div>
    </form>
</section>
<div class="buzon-summary">
    <section class="content-card"><p>Formularios recibidos</p><strong>{{ number_format($counts->sum()) }}</strong></section>
    @foreach($schema::OPINIONS as $value => $label)<section class="content-card"><p>{{ $label }}</p><strong>{{ number_format($counts[$value] ?? 0) }}</strong></section>@endforeach
</div>
<section class="content-card">
    <h2>Opiniones registradas</h2><p class="muted">Cada fila es un formulario del Buzón. No representa personas únicas y no se suma al informe de beneficiarios. Los totales corresponden a los filtros aplicados.</p>
    <div class="table-wrap"><table class="buzon-table"><thead><tr><th>Fecha de opinión</th><th>Proyecto</th><th>Lugar</th><th>Opinión</th><th>Retroalimentación</th><th>Nombre</th><th>Acciones</th></tr></thead><tbody>
        @forelse($entries as $entry)<tr>
            <td>{{ $entry->opinion_date->format('d/m/Y') }}</td><td><strong>{{ $entry->proyecto->codigo }}</strong><br>{{ $entry->proyecto->nombre_alias ?: $entry->proyecto->descripcion }}</td>
            <td>{{ $entry->place_name }}</td><td>{{ $schema::OPINIONS[$entry->opinion] }}</td><td>{{ $schema::FEEDBACK_TYPES[$entry->feedback_type] }}</td>
            <td>{{ $entry->full_name ?: 'Anónimo / Sin nombre' }}</td><td><a href="{{ route('buzon.show', $entry) }}">Ver formulario</a><br><a href="{{ route('buzon.edit', $entry) }}">Editar</a></td>
        </tr>@empty<tr><td colspan="7">No hay formularios del Buzón que coincidan con los filtros.</td></tr>@endforelse
    </tbody></table></div>
    <div class="pagination">{{ $entries->links() }}</div>
</section>
@endsection
@push('scripts')<script src="{{ asset('js/buzon.js') }}?v={{ filemtime(public_path('js/buzon.js')) }}"></script>@endpush
