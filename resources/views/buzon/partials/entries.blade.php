<section class="content-card" id="buzon-opinions">
    <h2>{{ $publicOnly ? 'Consultar opiniones públicas' : 'Consultar opiniones' }}</h2>
    <form method="get" action="{{ route($entriesRoute) }}" class="form-grid two-cols" data-buzon-filters>
        <label class="span-two">Proyectos
            <select name="project_ids[]" id="buzon-project-filter" multiple data-placeholder="Todos los proyectos disponibles">
                @foreach($projects as $project)<option value="{{ $project->id }}" @selected(in_array((string) $project->id, array_map('strval', $filters['project_ids']), true))>{{ $project->codigo }} — {{ $project->nombre_alias ?: $project->descripcion }}{{ $project->estatus ? '' : ' (Inactivo)' }}</option>@endforeach
            </select><small>Seleccione uno o varios. Sin selección se consultan todos sus proyectos disponibles.</small>
        </label>
        <label>Fecha de la opinión desde<input type="date" name="from" id="buzon-from" value="{{ $filters['from'] }}"></label>
        <label>Fecha de la opinión hasta<input type="date" name="to" id="buzon-to" value="{{ $filters['to'] }}"></label>
        <label>Tipo de opinión<select name="opinion"><option value="">Todas</option>@foreach($schema::OPINIONS as $value => $label)<option value="{{ $value }}" @selected($filters['opinion'] === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>Tipo de retroalimentación<select name="feedback_type"><option value="">Todos</option>@foreach($schema::FEEDBACK_TYPES as $value => $label)<option value="{{ $value }}" @selected($filters['feedback_type'] === $value)>{{ $label }}</option>@endforeach</select></label>
        <label>Grupo de edad<select name="age_group"><option value="">Todos</option>@foreach($schema::AGE_GROUPS as $value => $label)<option value="{{ $value }}" @selected($filters['age_group'] === $value)>{{ $label }}</option>@endforeach<option value="unspecified" @selected($filters['age_group'] === 'unspecified')>No indicado</option></select></label>
        <div class="form-actions span-two"><a class="button button-secondary" href="{{ route($entriesRoute) }}">Limpiar filtros</a><button class="button button-primary" type="submit">Consultar Buzón</button></div>
    </form>
</section>
<div class="buzon-summary">
    <section class="content-card"><p>Formularios recibidos</p><strong>{{ number_format($counts->sum()) }}</strong></section>
    @foreach($schema::OPINIONS as $value => $label)<section class="content-card"><p>{{ $label }}</p><strong>{{ number_format($counts[$value] ?? 0) }}</strong></section>@endforeach
</div>
<section class="content-card">
    <h2>{{ $publicOnly ? 'Opiniones recibidas por enlace externo' : 'Opiniones registradas' }}</h2><p class="muted">Cada fila es un formulario del Buzón. No representa personas únicas y no se suma al informe de beneficiarios. Los totales corresponden a los filtros aplicados.</p>
    <p id="buzon-export-status" class="muted" role="status" hidden></p>
    <div class="table-wrap"><table id="buzon-table" class="buzon-table display" style="width:100%" data-url="{{ route($entriesRoute) }}" data-filters="{{ json_encode($filters) }}" data-can-export="{{ auth()->user()->can('exportar buzon') ? '1' : '0' }}"><thead><tr><th>Fecha de opinión</th><th>Proyecto</th><th>Lugar</th><th>Opinión</th><th>Retroalimentación</th><th>Nombre</th><th>Usuario</th><th>Origen</th><th>Grupo de edad</th><th>Acciones</th></tr></thead><tbody>
        @forelse($entries as $entry)<tr>
            <td>{{ $entry->opinion_date->format('d/m/Y') }}</td><td><strong>{{ $entry->proyecto->codigo }}</strong><br>{{ $entry->proyecto->nombre_alias ?: $entry->proyecto->descripcion }}</td>
            <td>{{ $entry->place_name }}</td><td>{{ $schema::OPINIONS[$entry->opinion] }}</td><td>{{ $schema::FEEDBACK_TYPES[$entry->feedback_type] }}</td>
            <td>{{ $entry->full_name ?: 'Anónimo / Sin nombre' }}</td>
            <td>@if($entry->submitted_via_public_link)
                Externo (sin usuario)<small class="d-block muted">Responsable del enlace: {{ $entry->publicRecipient?->name ?: 'Usuario no disponible' }}</small>
                @else{{ $entry->creator?->name ?: 'Usuario no disponible' }}@endif
            </td>
            <td>{{ $entry->submitted_via_public_link ? 'Enlace externo' : 'Interno' }}</td><td>{{ $schema::AGE_GROUPS[$entry->age_group] ?? 'No indicado' }}</td><td>
                @include('buzon.partials.entry-actions', ['manageableIds' => $manageableEntryIds])
            </td>
        </tr>@empty @endforelse
    </tbody></table></div>
    <div class="pagination" data-buzon-fallback-pagination>{{ $entries->links() }}</div>
</section>
@push('scripts')
<script src="{{ asset('vendor/datatables/dataTables.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.responsive.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/dataTables.buttons.min.js') }}"></script>
@can('exportar buzon')
<script src="{{ asset('vendor/datatables/jszip.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/pdfmake.min.js') }}"></script>
<script src="{{ asset('vendor/datatables/vfs_fonts.js') }}"></script>
<script src="{{ asset('vendor/datatables/buttons.html5.min.js') }}"></script>
@endcan
<script src="{{ asset('js/buzon-table.js') }}?v={{ filemtime(public_path('js/buzon-table.js')) }}"></script>
@endpush
