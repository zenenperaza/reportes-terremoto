<script src="{{ asset('js/beneficiary-indicator-picker.js') }}?v={{ filemtime(public_path('js/beneficiary-indicator-picker.js')) }}" defer></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const select = id => document.getElementById(id);
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
});
</script>
