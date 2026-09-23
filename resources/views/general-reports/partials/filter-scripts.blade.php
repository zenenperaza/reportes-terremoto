<script>
document.addEventListener('DOMContentLoaded', () => {
    const select = id => document.getElementById(id);
    const sector = select('general_sector_id'), indicator = select('general_indicator_id');
    const availableIndicators = {{ Illuminate\Support\Js::from($indicators) }};
    const ageFrom = select('general_age_from'), ageTo = select('general_age_to'), ageGroup = select('general_age_group');
    const synchronizeAgeFilters = source => {
        if (source === ageGroup && ageGroup.value) {
            ageFrom.value = '';
            ageTo.value = '';
        } else if ((source === ageFrom || source === ageTo) && (ageFrom.value !== '' || ageTo.value !== '')) {
            ageGroup.value = '';
        }
    };
    ageFrom?.addEventListener('input', () => synchronizeAgeFilters(ageFrom));
    ageTo?.addEventListener('input', () => synchronizeAgeFilters(ageTo));
    ageGroup?.addEventListener('change', () => synchronizeAgeFilters(ageGroup));
    const selectAllIndicatorsValue = '__select_all_indicators__';
    const addSelectAllIndicatorsOption = () => {
        if (!indicator) return;
        const option = new Option('Seleccionar todos los indicadores', selectAllIndicatorsValue);
        option.disabled = !Array.from(indicator.options).some(item => item.value && !item.disabled);
        indicator.prepend(option);
    };
    const selectAllIndicators = () => {
        if (!indicator) return;
        Array.from(indicator.options).forEach(option => {
            option.selected = Boolean(option.value && option.value !== selectAllIndicatorsValue && !option.disabled);
        });
        indicator.dispatchEvent(new Event('change', {bubbles: true}));
    };
    const fillIndicators = () => {
        if (!indicator) return;
        const selectedValues = Array.from(indicator.selectedOptions).map(option => option.value);
        const sectorId = Number(sector?.value || 0);
        const options = sectorId
            ? availableIndicators.filter(item => item.sector_ids.map(Number).includes(sectorId))
            : availableIndicators;
        indicator.replaceChildren();
        options.forEach(item => indicator.add(new Option(item.label, String(item.id))));
        Array.from(indicator.options).forEach(option => {
            option.selected = selectedValues.includes(option.value) && options.some(item => String(item.id) === option.value);
        });
        // The bulk action is UI-only; never send it as an indicator filter.
        addSelectAllIndicatorsOption();
        window.jQuery?.(indicator).trigger('change');
    };
    if (indicator) {
        addSelectAllIndicatorsOption();
        indicator.addEventListener('change', () => {
            if (Array.from(indicator.selectedOptions).some(option => option.value === selectAllIndicatorsValue)) {
                selectAllIndicators();
            }
        });
    }
    if (window.jQuery?.fn?.select2) {
        window.jQuery(indicator).select2({
            width: '100%', placeholder: 'Todos los indicadores', closeOnSelect: false, allowClear: true,
            dropdownCssClass: 'general-indicator-dropdown',
            language: {noResults: () => 'No se encontraron indicadores', searching: () => 'Buscando...'},
        }).on('select2:selecting', event => {
            if (event.params.args.data.id !== selectAllIndicatorsValue) return;
            event.preventDefault();
            selectAllIndicators();
            window.jQuery(indicator).select2('close');
        });
    }
    sector?.addEventListener('change', fillIndicators);
});
</script>
