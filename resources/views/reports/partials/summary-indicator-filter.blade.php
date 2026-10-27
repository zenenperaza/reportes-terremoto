@php($summarySelection = array_map('strval', (array) ($filters['summary_indicator_ids'] ?? [])))
<label class="summary-indicator-filter">Indicadores Resumen
    <select name="summary_indicator_ids[]" multiple data-summary-indicator-filter data-placeholder="Todos (sin filtro)">
        <option value="__all__" data-summary-select-all disabled>Seleccionar todos</option>
        @foreach($summaryIndicatorOptions as $summary)<option value="{{ $summary->id }}" @selected(in_array((string) $summary->id, $summarySelection, true))>{{ $summary->proyecto?->codigo }} / {{ $summary->code }} — {{ $summary->name }}</option>@endforeach
    </select><small>Seleccione uno o varios resúmenes, o use «Seleccionar todos» y quite los que no necesite. Se incluyen sus indicadores sin duplicar registros; se mantienen los demás filtros, permisos y exclusiones del informe.</small>
</label>
@once @push('scripts')<script src="{{ asset('js/summary-indicator-filter.js') }}?v={{ filemtime(public_path('js/summary-indicator-filter.js')) }}"></script>@endpush @endonce
