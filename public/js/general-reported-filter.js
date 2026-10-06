(() => {
    const form = document.getElementById('general-reported-filter');
    const reported = document.getElementById('general_reported');
    const periods = document.getElementById('reporting-period');
    const container = document.getElementById('general-reported-periods');
    if (!form || !reported || !periods || !container) return;

    const syncPeriods = () => {
        const values = Array.from(periods.selectedOptions, option => option.value);
        container.replaceChildren(...(values.length ? values : ['']).map(value => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'reporting_period[]';
            input.value = value;
            return input;
        }));
    };
    periods.addEventListener('change', syncPeriods);
    reported.addEventListener('change', () => {
        syncPeriods();
        form.requestSubmit();
    });
    form.addEventListener('submit', syncPeriods);
    syncPeriods();
})();
