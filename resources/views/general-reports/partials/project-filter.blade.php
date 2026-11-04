<section class="content-card beneficiary-project-card">
    <form method="get" action="{{ route('general-reports.index') }}" id="general-project-scope">
        <input type="hidden" name="reported" value="{{ $filters['reported'] ?? '' }}">
        <label class="beneficiary-project-filter" for="general_project_ids">Proyectos
            <select id="general_project_ids" name="project_ids[]" multiple data-general-project-filter data-placeholder="Todos los proyectos" aria-describedby="general-project-help">
                <option value="__all__" data-project-select-all disabled>Seleccionar todos los proyectos</option>
                @foreach($projectOptions as $project)
                    <option value="{{ $project->id }}" @selected(in_array($project->id, $filters['project_ids'], true))>{{ $project->codigo }} — {{ $project->nombre_alias ?: $project->descripcion }}</option>
                @endforeach
            </select>
            <small id="general-project-help">Sin selección se incluyen todos los proyectos. Seleccione uno o varios, o seleccione todos y quite los que no necesite. Al cerrar la lista se actualizan los demás filtros y se conserva el estado de reporte.</small>
        </label>
        <button class="button button-primary" type="submit">Aplicar proyectos</button>
        <small id="general-project-status" role="status" aria-live="polite"></small>
    </form>
</section>
@push('scripts')
    <script src="{{ asset('js/general-project-filter.js') }}?v={{ filemtime(public_path('js/general-project-filter.js')) }}" defer></script>
@endpush
