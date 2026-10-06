<section class="content-card general-reported-card" aria-labelledby="general-reported-title">
    <h2 id="general-reported-title">Estado de reporte</h2>
    <form method="get" action="{{ url()->current() }}" id="general-reported-filter" class="general-reported-filter">
        <span id="general-reported-periods" hidden>
            @foreach(\App\Support\ReportPeriod::selection($filters['reporting_period'] ?? null) ?: [''] as $selectedPeriod)
                <input type="hidden" name="reporting_period[]" value="{{ $selectedPeriod }}">
            @endforeach
        </span>
        <label for="general_reported">Reportado
            <select name="reported" id="general_reported" aria-describedby="general-reported-help">
                <option value="0" @selected(($filters['reported'] ?? '') === '0')>No reportados</option>
                <option value="1" @selected(($filters['reported'] ?? '') === '1')>Sí reportados</option>
                <option value="" @selected(($filters['reported'] ?? '') === '')>Todos</option>
            </select>
        </label>
        <button class="button button-primary" type="submit">Aplicar estado</button>
        <p class="muted" id="general-reported-help">Los indicadores, lugares y demás opciones corresponden al estado elegido. Al cambiarlo se conserva el período y se limpian los demás filtros.</p>
    </form>
</section>
@once
    @push('scripts')
        <script src="{{ asset('js/general-reported-filter.js') }}?v={{ filemtime(public_path('js/general-reported-filter.js')) }}" defer></script>
    @endpush
@endonce
