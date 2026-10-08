<?php

namespace App\Services;

use App\Support\ReportPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProgrammedServicesExcelExport
{
    public function downloadDeliveries(Collection $rows, array $filters): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $filters): void {
            $book = new Spreadsheet;
            try {
                $mode = $filters['vista'] === 'beneficiario' ? 'Por beneficiario' : 'Por servicio';
                $book->getProperties()->setCreator('SIA - ASONACOP')->setTitle('Servicios entregados — '.$mode);
                $sheet = $book->getActiveSheet()->setTitle('Servicios entregados');
                $sheet->mergeCells('A1:L1')->setCellValueExplicit('A1', $mode.' — No representa personas únicas entre registros ni cantidades de unidades entregadas.', DataType::TYPE_STRING);
                $headers = ['ID beneficiario', 'ID registro', 'Beneficiario', 'Edad', 'Sexo', 'Servicios entregados',
                    'Fecha de atención', 'Período', 'Proyecto', 'Sector', 'Indicador', 'Actividad'];
                foreach ($headers as $column => $label) {
                    $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($column + 1).'3', $label, DataType::TYPE_STRING);
                }
                $line = 4;
                foreach ($rows as $row) {
                    $person = $row['beneficiary'];
                    $report = $person->report;
                    $values = [$person->id, $report->id, $person->full_name ?: 'Sin nombre registrado', (int) $person->age, $person->sex,
                        $row['services']->map(fn ($service) => $service->servicio?->nombre ?? 'Sin descripción')->implode(' | '),
                        $report->report_date->format('Y-m-d'), ReportPeriod::label($report->reporting_period),
                        $report->proyecto?->codigo ?? 'Sin proyecto', $report->indicadorProyecto?->asignacionSector?->sector?->name ?? 'Sin sector asignado',
                        $report->indicadorProyecto?->indicador?->codigo ?? 'Sin indicador', $report->actividadIndicador?->actividad?->descripcion ?? 'Sin actividad'];
                    foreach ($values as $column => $value) {
                        $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($column + 1).$line, $value,
                            is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                    }
                    $line++;
                }
                $sheet->freezePane('A4')->setAutoFilter('A3:L'.max(3, $line - 1));
                $sheet->getStyle('A3:L3')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0B3B60']],
                ]);
                $sheet->getStyle('A1:L'.max(3, $line - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                foreach ([16, 16, 36, 10, 14, 48, 20, 22, 26, 28, 35, 40] as $column => $width) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column + 1))->setWidth($width);
                }
                $sheet->getRowDimension(1)->setRowHeight(32);
                $sheet->getRowDimension(3)->setRowHeight(30);
                $filterSheet = $book->createSheet()->setTitle('Filtros');
                $filterSheet->setCellValue('A1', 'Filtro')->setCellValue('B1', 'Valor / ID seleccionado');
                $labels = ['vista' => 'Vista', 'proyecto_id' => 'Proyecto', 'sector_id' => 'Sector', 'indicador_id' => 'Indicador',
                    'actividad_id' => 'Actividad', 'servicio_id' => 'Servicio', 'estatus' => 'Estado de la asignación',
                    'reporting_period' => 'Períodos', 'from' => 'Fecha de atención desde', 'to' => 'Fecha de atención hasta'];
                $line = 2;
                foreach ($labels as $field => $label) {
                    $value = $filters[$field] ?? '';
                    if ($field === 'vista') {
                        $value = $mode;
                    }
                    if ($field === 'reporting_period') {
                        $value = implode(', ', array_map(fn ($period) => ReportPeriod::label($period), ReportPeriod::selection($value)));
                    }
                    $value = ! filled($value) ? 'Todos' : ($field === 'estatus' ? ((string) $value === '1' ? 'Activo' : 'Inactivo') : (string) $value);
                    $filterSheet->setCellValueExplicit('A'.$line, $label, DataType::TYPE_STRING);
                    $filterSheet->setCellValueExplicit('B'.$line, $value, DataType::TYPE_STRING);
                    $line++;
                }
                $filterSheet->getColumnDimension('A')->setWidth(28);
                $filterSheet->getColumnDimension('B')->setWidth(38);
                $book->setActiveSheetIndex(0);
                $writer = new Xlsx($book);
                $writer->setPreCalculateFormulas(false);
                $writer->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'servicios-entregados-'.$filters['vista'].'-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function download(Builder $assignments, array $filters): StreamedResponse
    {
        return response()->streamDownload(function () use ($assignments, $filters): void {
            $book = new Spreadsheet;
            try {
                $book->getProperties()->setCreator('SIA - ASONACOP')->setTitle('Informes por Servicios');
                $sheet = $book->getActiveSheet()->setTitle('Informes por Servicios');
                $sheet->mergeCells('A1:O1')->setCellValue('A1', 'Servicios seleccionados en registros con beneficiarios. Las atenciones no son personas únicas ni unidades entregadas. SIA no registra cantidades entregadas por servicio.');
                $headers = ['ID asignación', 'Servicio', 'Descripción del servicio', 'Proyecto', 'Sector', 'Indicador', 'Actividad',
                    'Atenciones de beneficiarios con servicio', 'Estado de la asignación', 'Registros con servicio', 'Estado del proyecto', 'Estado del indicador', 'Estado de la actividad', 'Primera atención', 'Última atención'];
                foreach ($headers as $column => $label) {
                    $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($column + 1).'3', $label, DataType::TYPE_STRING);
                }
                $row = 4;
                // Export all matching rows, not just the visible page; eager load in batches.
                foreach ($assignments->lazy(250) as $assignment) {
                    $activity = $assignment->actividadIndicador;
                    $indicator = $activity->indicadorProyecto;
                    $values = [
                        $assignment->id, $assignment->servicio->nombre, $assignment->servicio->descripcion ?? '',
                        $indicator->proyecto->codigo, $indicator->asignacionSector?->sector?->name ?? 'Sin sector asignado',
                        $indicator->indicador->codigo.' — '.$indicator->indicador->descripcion,
                        $activity->actividad->codigo.' — '.$activity->actividad->descripcion,
                        (int) $assignment->beneficiaries_count,
                        $assignment->estatus ? 'Activo' : 'Inactivo', (int) $assignment->reports_count,
                        $indicator->proyecto->estatus ? 'Activo' : 'Inactivo', $indicator->estatus ? 'Activo' : 'Inactivo',
                        $activity->estatus ? 'Activo' : 'Inactivo',
                        $assignment->reports_min_report_date ? Carbon::parse($assignment->reports_min_report_date)->format('Y-m-d') : 'Sin fecha',
                        $assignment->reports_max_report_date ? Carbon::parse($assignment->reports_max_report_date)->format('Y-m-d') : 'Sin fecha',
                    ];
                    foreach ($values as $column => $value) {
                        $sheet->setCellValueExplicit(Coordinate::stringFromColumnIndex($column + 1).$row, $value,
                            is_int($value) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
                    }
                    $row++;
                }
                $sheet->freezePane('A4')->setAutoFilter('A3:O'.max(3, $row - 1));
                $sheet->getStyle('A3:O3')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0B3B60']],
                ]);
                $sheet->getStyle('A1:O'.max(3, $row - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getRowDimension(1)->setRowHeight(32);
                $sheet->getRowDimension(3)->setRowHeight(36);
                foreach ([14, 28, 40, 24, 24, 55, 50, 25, 18, 22, 18, 18, 18, 18, 18] as $column => $width) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column + 1))->setWidth($width);
                }
                $filterSheet = $book->createSheet()->setTitle('Filtros');
                $filterSheet->setCellValue('A1', 'Filtro')->setCellValue('B1', 'Valor / ID seleccionado');
                $labels = ['proyecto_id' => 'Proyecto', 'sector_id' => 'Sector', 'indicador_id' => 'Indicador',
                    'actividad_id' => 'Actividad', 'servicio_id' => 'Servicio', 'estatus' => 'Estado de la asignación',
                    'reporting_period' => 'Períodos', 'from' => 'Fecha de atención desde', 'to' => 'Fecha de atención hasta'];
                foreach ($labels as $field => $label) {
                    $line = array_search($field, array_keys($labels), true) + 2;
                    $value = $filters[$field] ?? '';
                    if ($field === 'reporting_period') {
                        $value = implode(', ', array_map(fn ($period) => ReportPeriod::label($period), ReportPeriod::selection($value)));
                    }
                    $value = ! filled($value) ? 'Todos' : ($field === 'estatus' ? ((string) $value === '1' ? 'Activo' : 'Inactivo') : (string) $value);
                    $filterSheet->setCellValueExplicit('A'.$line, $label, DataType::TYPE_STRING);
                    $filterSheet->setCellValueExplicit('B'.$line, $value, DataType::TYPE_STRING);
                }
                $filterSheet->getColumnDimension('A')->setWidth(28);
                $filterSheet->getColumnDimension('B')->setWidth(28);
                $book->setActiveSheetIndex(0);
                $writer = new Xlsx($book);
                $writer->setPreCalculateFormulas(false);
                $writer->save('php://output');
            } finally {
                $book->disconnectWorksheets();
            }
        }, 'informes-por-servicios-'.now()->format('Ymd-His').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
