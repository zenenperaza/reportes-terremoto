<label for="reporting-period">Período
    <select name="reporting_period" id="reporting-period" @isset($periodForm) form="{{ $periodForm }}" @endisset>
        <option value="">Todos los períodos</option>
        @foreach($periodOptions as $value => $label)<option value="{{ $value }}" @selected(($filters['reporting_period'] ?? '') === $value)>{{ $label }}</option>@endforeach
        <option value="unassigned" @selected(($filters['reporting_period'] ?? '') === 'unassigned')>Sin período asignado</option>
    </select>
</label>
