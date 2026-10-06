@can('exportar registros excel')
    <div class="heading-actions">
        <a class="button button-primary" data-period-date-export href="{{ route($exportRoute, $filters) }}" title="Descargar el informe con los filtros aplicados">
            <i class="ri-file-excel-2-line" aria-hidden="true"></i> Exportar a Excel
        </a>
    </div>
@endcan
