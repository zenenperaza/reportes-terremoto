@php
    $selectedIndicators = $filters['indicator_filter'] ?? (!empty($filters['indicador_proyecto_id'])
        ? ['project:'.$filters['indicador_proyecto_id']]
        : (!empty($filters['activity_id']) ? ['legacy:'.$filters['activity_id']] : []));
    $cards = $indicatorOptions->groupBy('value')->map(function ($options) {
        return $options->first() + ['sector_ids' => $options->pluck('sector_id')->map(fn ($id) => (string) $id)->unique()->values()->all()];
    });
    $availableCards = $cards->filter(fn ($card) => empty($filters['sector_id']) || in_array((string) $filters['sector_id'], $card['sector_ids'], true));
    $selectedCount = $availableCards->whereIn('value', $selectedIndicators)->count();
    $cardGroups = $cards->groupBy(fn ($card) => $card['group_id'] ? 'group:'.$card['group_id'] : (str_starts_with($card['value'], 'legacy:') ? 'legacy' : 'ungrouped'), true)
        ->sortBy(fn ($group) => $group->first()['group_order'] ?? PHP_INT_MAX);
@endphp
<fieldset class="beneficiary-indicator-field" id="summary-indicator-picker" aria-describedby="summary-indicator-help">
    <legend>Indicador a reportar</legend>
    <input type="hidden" name="indicator_filter[]" value="">
    <div class="summary-indicator-toolbar">
        <button class="btn btn-primary" type="button" id="summary-indicator-toggle" data-bs-toggle="collapse" data-bs-target="#summary-indicator-panel" aria-expanded="false" aria-controls="summary-indicator-panel">
            <i class="ri-list-check-2" aria-hidden="true"></i> Seleccione indicador <i class="ri-arrow-down-s-line" aria-hidden="true"></i>
        </button>
        <span id="summary-indicator-selection" role="status" aria-live="polite">{{ $selectedCount ? $selectedCount.' seleccionado(s)' : 'Todos los indicadores (sin filtro)' }}</span>
    </div>
    <small class="muted" id="summary-indicator-help">Marque uno o varios indicadores. Puede seleccionar todos y desmarcar los que no necesite. Sin selección se incluyen todos.</small>
    <div class="collapse" id="summary-indicator-panel">
        <div class="summary-indicator-content">
            <div class="summary-indicator-tools">
                <label for="summary-indicator-search">Buscar indicador
                    <input type="search" id="summary-indicator-search" placeholder="Buscar por código, descripción o grupo" autocomplete="off">
                </label>
                <label class="summary-indicator-bulk"><input type="checkbox" id="summary-indicator-all" disabled> Seleccionar todos los grupos</label>
                <button type="button" class="btn btn-outline-secondary" id="summary-indicator-clear">Quitar selección</button>
            </div>
            <p class="muted summary-indicator-tools">La selección por grupo o de todos los grupos incluye sus indicadores disponibles en el sector elegido, aunque haya una búsqueda escrita. Puede desmarcarlos individualmente.</p>
            <div class="indicator-card-grid">
                @foreach($cardGroups as $groupKey => $group)
                    @php
                        $first = $group->first();
                        $groupName = $first['group_name'] ?: ($groupKey === 'legacy' ? 'Registros anteriores' : 'Sin grupo');
                        $visibleCount = $group->intersectByKeys($availableCards)->count();
                    @endphp
                    <section class="indicator-group-panel" data-indicator-group @if(!$visibleCount) hidden @endif>
                        <header class="indicator-group-header">
                            <strong>{{ $first['group_order'] !== null ? $first['group_order'].'. ' : '' }}{{ $groupName }}</strong>
                            <div class="summary-indicator-group-actions">
                                <label class="summary-indicator-bulk"><input type="checkbox" data-group-all disabled aria-label="Seleccionar todos los indicadores del grupo {{ $groupName }}"> Seleccionar todo el grupo</label>
                                <span class="indicator-group-total" data-group-count>{{ $visibleCount }}</span>
                            </div>
                        </header>
                        @if($first['group_description'])<p class="indicator-group-description">{{ $first['group_description'] }}</p>@endif
                        <div class="indicator-group-items">
                            @foreach($group->sortBy('code', SORT_NATURAL) as $card)
                                @php
                                    $available = $availableCards->has($card['value']);
                                    $checked = $available && in_array($card['value'], $selectedIndicators, true);
                                    $legacy = str_starts_with($card['value'], 'legacy:');
                                    $coordinationClass = match ($card['coordination']) { 'VBG' => 'is-vbg', 'NNA/VBG' => 'is-mixed', default => 'is-nna' };
                                @endphp
                                <label class="indicator-card summary-indicator-card {{ $checked ? 'is-selected' : '' }}" data-indicator-card data-sectors="{{ json_encode($card['sector_ids']) }}" data-search="{{ $card['label'].' '.$groupName.' '.$card['coordination'] }}" @if(!$available) hidden @endif>
                                    <span class="indicator-card-top">
                                        <span class="indicator-coordination {{ $coordinationClass }}">{{ $card['coordination'] ?: ($legacy ? 'Anterior' : 'General') }}</span>
                                        <strong>{{ $card['code'] ?: 'Registro anterior' }}</strong>
                                        <input type="checkbox" name="indicator_filter[]" value="{{ $card['value'] }}" @checked($checked) @disabled(!$available) aria-label="{{ $card['label'] }}">
                                    </span>
                                    <span class="indicator-card-description">{{ $card['title'] }}{{ $legacy ? ' (registro anterior)' : '' }}</span>
                                    @if(!$legacy)<span class="indicator-card-meta">{{ $card['unit'] ?: 'Sin unidad' }} · Edad: {{ $card['age_from'] ?? 0 }} a {{ $card['age_to'] ?? 120 }} años</span>@endif
                                </label>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>
            <p class="indicator-card-empty" id="summary-indicator-empty" @if($availableCards->isNotEmpty()) hidden @endif>No hay indicadores disponibles para este sector y estado de reporte.</p>
        </div>
    </div>
    <noscript><style>#summary-indicator-panel.collapse{display:block}#summary-indicator-toggle,.summary-indicator-tools,.summary-indicator-bulk{display:none!important}</style></noscript>
</fieldset>
