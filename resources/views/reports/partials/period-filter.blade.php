@php($selectedPeriods = \App\Support\ReportPeriod::selection($filters['reporting_period'] ?? null))
<label for="reporting-period">Período
    <input type="hidden" name="reporting_period[]" value="" @isset($periodForm) form="{{ $periodForm }}" @endisset>
    <select name="reporting_period[]" id="reporting-period" multiple aria-describedby="reporting-period-help" @isset($periodForm) form="{{ $periodForm }}" @endisset>
        @foreach($periodOptions as $value => $label)<option value="{{ $value }}" @selected(in_array($value, $selectedPeriods, true))>{{ $label }}</option>@endforeach
        <option value="unassigned" @selected(in_array('unassigned', $selectedPeriods, true))>Sin período asignado</option>
    </select>
    <small id="reporting-period-help">Seleccione uno o varios. Sin selección se incluyen todos los períodos.</small>
</label>
@once
    @push('scripts')
        <script src="{{ asset('js/period-filter.js') }}?v={{ filemtime(public_path('js/period-filter.js')) }}" defer></script>
    @endpush
@endonce
