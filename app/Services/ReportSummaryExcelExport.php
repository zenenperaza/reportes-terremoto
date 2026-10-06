<?php

namespace App\Services;

use App\Support\ReportPeriod;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportSummaryExcelExport
{
    public function download(array $data, bool $byIndicators): StreamedResponse
    {
        $workbook = new Spreadsheet;
        $workbook->getProperties()->setCreator('SIA - ASONACOP')->setTitle($byIndicators ? 'Informe por indicadores' : 'Informes generales');
        $summary = $workbook->getActiveSheet();
        $summary->setTitle('Resumen');
        $this->table($summary, $byIndicators ? 'Informe por indicadores' : 'Informes generales', ['Concepto', 'Cantidad'], [
            ['Personas atendidas', $data['summary']['beneficiaries']],
            ['NNA Mujeres', $data['summary']['women_under_18']],
            ['NNA Hombres', $data['summary']['men_under_18']],
            ['Mujeres adultas', $data['summary']['women_adults']],
            ['Hombres adultos', $data['summary']['men_adults']],
        ]);
        $this->table($summary, 'Filtros aplicados', ['Filtro', 'Valor'], $this->filterRows($data), 11);
        $summary->getColumnDimension('A')->setWidth(38);
        $summary->getColumnDimension('B')->setWidth(75);

        if ($byIndicators) {
            $this->indicatorSheets($workbook, $data);
        } else {
            $this->generalSheet($workbook, $data['charts']);
        }
        $workbook->setActiveSheetIndex(0);
        $filename = ($byIndicators ? 'informe-indicadores-' : 'informes-generales-').now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($workbook): void {
            try {
                $writer = new Xlsx($workbook);
                $writer->setPreCalculateFormulas(false);
                $writer->save('php://output');
            } finally {
                $workbook->disconnectWorksheets();
            }
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function generalSheet(Spreadsheet $workbook, array $charts): void
    {
        $sheet = $workbook->createSheet()->setTitle('Desgloses');
        $row = 1;
        $ageRows = [];
        foreach ($charts['ages']['labels'] as $index => $label) {
            $men = $charts['ages']['men'][$index];
            $women = $charts['ages']['women'][$index];
            $ageRows[] = [$label, $men, $women, $men + $women];
        }
        $row = $this->table($sheet, 'Beneficiarios por grupo etario y sexo', ['Grupo etario', 'Hombres', 'Mujeres', 'Total'], $ageRows, $row);
        $sexRows = [];
        foreach ($charts['sex']['labels'] as $index => $label) {
            $sexRows[] = [$label, $charts['sex']['values'][$index]];
        }
        $row = $this->table($sheet, 'Distribución por sexo', ['Sexo', 'Beneficiarios'], $sexRows, $row);
        foreach (['attention_types' => ['Tipo de atención', 'Tipo de espacio o instalación'], 'states' => ['Distribución territorial (diez estados con más beneficiarios)', 'Estado']] as $key => [$title, $label]) {
            $rows = [];
            foreach ($charts[$key]['labels'] as $index => $value) {
                $rows[] = [$value, $charts[$key]['values'][$index]];
            }
            $row = $this->table($sheet, $title, [$label, 'Beneficiarios'], $rows, $row);
        }
        $trendRows = [];
        foreach ($charts['trend']['labels'] as $index => $date) {
            $men = $charts['trend']['men'][$index];
            $women = $charts['trend']['women'][$index];
            $trendRows[] = [Date::PHPToExcel(Carbon::parse($date)->startOfDay()), $men, $women, $men + $women];
        }
        $start = $row + 2;
        $this->table($sheet, 'Evolución de atenciones', ['Fecha de atención', 'Hombres', 'Mujeres', 'Total'], $trendRows, $row);
        if ($trendRows) {
            $sheet->getStyle('A'.$start.':A'.($start + count($trendRows) - 1))->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        }
        $sheet->getColumnDimension('A')->setWidth(62);
        foreach (['B', 'C', 'D'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(20);
        }
    }

    private function indicatorSheets(Spreadsheet $workbook, array $data): void
    {
        $groups = $workbook->createSheet()->setTitle('Grupos');
        $indicators = $workbook->createSheet()->setTitle('Indicadores');
        $groupRows = [];
        $indicatorRows = [];
        $assignedTotal = 0;
        foreach ($data['indicatorGroupsSummary'] as $group) {
            $groupRows[] = [$group['name'], $group['description'], $group['indicator_count'], $group['women'], $group['men'], $group['beneficiaries']];
            $assignedTotal += $group['beneficiaries'];
            foreach ($group['items'] as $indicator) {
                $indicatorRows[] = [$group['name'], $indicator['code'], $indicator['title'], $indicator['unit'], $indicator['coordination_space'],
                    $indicator['age_from'], $indicator['age_to'], $indicator['women'], $indicator['men'], $indicator['beneficiaries']];
            }
        }
        $end = $this->table($groups, 'Beneficiarios por grupo de indicadores', ['Grupo', 'Descripción', 'Indicadores', 'Mujeres', 'Hombres', 'Total'], $groupRows, filter: true);
        $this->table($indicators, 'Beneficiarios por indicador', ['Grupo', 'Código', 'Indicador', 'Unidad de conteo', 'Espacio de coordinación', 'Edad desde', 'Edad hasta', 'Mujeres', 'Hombres', 'Total'], $indicatorRows, filter: true);
        // El resumen incluye registros históricos sin indicador de proyecto; no inventar un indicador para ellos.
        $this->table($groups, 'Conciliación con el resumen', ['Concepto', 'Beneficiarios'], [
            ['Con indicador de proyecto', $assignedTotal],
            ['Sin indicador de proyecto disponible', $data['summary']['beneficiaries'] - $assignedTotal],
            ['Total del resumen', $data['summary']['beneficiaries']],
        ], $end);
        $groups->getColumnDimension('A')->setWidth(38);
        $groups->getColumnDimension('B')->setWidth(65);
        $indicators->getColumnDimension('A')->setWidth(38);
        $indicators->getColumnDimension('B')->setWidth(30);
        $indicators->getColumnDimension('C')->setWidth(72);
        $indicators->getColumnDimension('D')->setWidth(30);
        $indicators->getColumnDimension('E')->setWidth(25);
    }

    private function table(Worksheet $sheet, string $title, array $headers, array $rows, int $start = 1, bool $filter = false): int
    {
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->mergeCells('A'.$start.':'.$lastColumn.$start);
        $this->cell($sheet, 'A'.$start, $title);
        $sheet->getStyle('A'.$start)->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF123D55');
        $sheet->getRowDimension($start)->setRowHeight(26);
        $headerRow = $start + 1;
        foreach ($headers as $index => $value) {
            $this->cell($sheet, Coordinate::stringFromColumnIndex($index + 1).$headerRow, $value);
        }
        $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF405189']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);
        $row = $headerRow + 1;
        foreach ($rows as $values) {
            foreach ($values as $index => $value) {
                $this->cell($sheet, Coordinate::stringFromColumnIndex($index + 1).$row, $value);
            }
            $sheet->getRowDimension($row)->setRowHeight(45);
            if (($row - $headerRow) % 2 === 0) {
                $sheet->getStyle('A'.$row.':'.$lastColumn.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3F6F9');
            }
            $row++;
        }
        if ($rows) {
            $sheet->getStyle('A'.($headerRow + 1).':'.$lastColumn.($row - 1))->applyFromArray([
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                'borders' => ['bottom' => ['borderStyle' => Border::BORDER_HAIR, 'color' => ['argb' => 'FFE2E8F0']]],
            ]);
        }
        if ($filter) {
            $sheet->setAutoFilter('A'.$headerRow.':'.$lastColumn.max($headerRow, $row - 1));
        }
        $sheet->freezePane('A3');
        $sheet->getDefaultColumnDimension()->setWidth(20);
        $sheet->setShowGridlines(false);
        $sheet->getPageSetup()->setOrientation('landscape')->setFitToWidth(1)->setFitToHeight(0);

        return $row + 2;
    }

    private function cell(Worksheet $sheet, string $cell, mixed $value): void
    {
        // Los textos de catálogos y filtros se escriben como texto, nunca como fórmulas de Excel.
        $sheet->setCellValueExplicit($cell, $value ?? '', is_int($value) || is_float($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        if (is_int($value)) {
            $sheet->getStyle($cell)->getNumberFormat()->setFormatCode('#,##0');
        }
    }

    private function filterRows(array $data): array
    {
        $filters = $data['filters'];
        $rows = [
            ['Reportado', ($filters['reported'] ?? '') === '' || ($filters['reported'] ?? null) === null ? 'Todos' : ((string) $filters['reported'] === '1' ? 'Sí' : 'No')],
            ['Período', implode(', ', array_map(fn ($period) => ReportPeriod::label($period), ReportPeriod::selection($filters['reporting_period'] ?? ''))) ?: 'Todos los períodos'],
        ];
        foreach (['attention_from' => 'Fecha de atención desde', 'attention_to' => 'Fecha de atención hasta', 'registered_from' => 'Fecha de registro desde', 'registered_to' => 'Fecha de registro hasta'] as $key => $label) {
            $rows[] = [$label, filled($filters[$key] ?? null) ? Carbon::parse($filters[$key])->format('d/m/Y') : 'Sin límite'];
        }
        $rows[] = ['Edad desde', filled($filters['age_from'] ?? null) ? (int) $filters['age_from'] : 'Todas'];
        $rows[] = ['Edad hasta', filled($filters['age_to'] ?? null) ? (int) $filters['age_to'] : 'Todas'];
        $rows[] = ['Grupo etario', $data['ageGroups'][$filters['age_group'] ?? '']['label'] ?? 'Todos'];
        $rows[] = ['Sexo', ($filters['sex'] ?? '') ?: 'Todos'];
        foreach (['state_id' => ['Estado', 'states'], 'municipality_id' => ['Municipio', 'municipalities'], 'parish_id' => ['Parroquia', 'parishes'], 'sector_id' => ['Sector', 'sectors'], 'indicador_id' => ['Indicadores', 'indicators']] as $key => [$label, $options]) {
            $selected = array_filter((array) ($filters[$key] ?? []), fn ($id) => filled($id));
            $names = collect($data[$options])->mapWithKeys(fn ($option) => [(string) data_get($option, 'id') => data_get($option, 'label', data_get($option, 'name'))]);
            $rows[] = [$label, implode(', ', array_map(fn ($id) => $names->get((string) $id) ?? 'ID '.$id, $selected)) ?: 'Todos'];
        }
        $rows[] = ['Tipo de atención', ($filters['installation_type'] ?? '') ?: 'Todos'];
        $rows[] = ['Nombre del lugar', ($filters['place_name'] ?? '') ?: 'Todos'];
        $rows[] = ['Recurrente', filled($filters['is_recurrent'] ?? null) ? ((string) $filters['is_recurrent'] === '1' ? 'Sí' : 'No') : 'Todos'];

        return $rows;
    }
}
