@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/report-excel-button.css') }}?v={{ filemtime(public_path('css/report-excel-button.css')) }}">
    @endpush
@endonce
@can($exportPermission ?? 'exportar registros excel')
    <a class="button button-excel" id="{{ $exportId ?? 'general-report-export' }}" data-period-date-export
       href="{{ route($exportRoute, $filters) }}" title="Descargar los resultados del informe generado con los filtros aplicados">
        <svg class="excel-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path d="M13 3h8v18h-8v-3h5v-2h-5v-2h5v-2h-5v-2h5V8h-5V6h5V5h-5V3Z" fill="currentColor" opacity=".72"/>
            <path d="M3 5.2 14 3v18L3 18.8V5.2Zm3.2 3.1 2 3.6-2.2 3.8h2.1l1.2-2.3 1.3 2.3h2.1l-2.2-3.9 2-3.5h-2L9.4 10.4 8.2 8.3h-2Z" fill="currentColor"/>
        </svg>
        Exportar Excel
    </a>
@endcan
