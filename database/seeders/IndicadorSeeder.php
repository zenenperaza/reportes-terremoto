<?php

namespace Database\Seeders;

use App\Models\Indicador;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

class IndicadorSeeder extends Seeder
{
    private const FILE_NAME = 'Indicadores con tipo poblacion atendida2 (1).xlsx';

    private const EXPECTED_HEADERS = [
        'Codigo Indicador',
        'Nombre Indicador',
        'Unidad de conteo',
        'Espacio de coordinacion',
        'Poblacion Dirigida',
    ];

    public function run(): void
    {
        $path = base_path('docs/'.self::FILE_NAME);

        if (! is_file($path)) {
            throw new RuntimeException("No se encontró el archivo de indicadores: {$path}");
        }

        $worksheet = IOFactory::load($path)->getActiveSheet();
        $rows = $worksheet->toArray(null, true, true, false);
        $headers = array_map(fn ($value) => trim((string) $value), array_shift($rows) ?? []);

        if (array_slice($headers, 0, count(self::EXPECTED_HEADERS)) !== self::EXPECTED_HEADERS) {
            throw new RuntimeException('El archivo de indicadores no contiene las columnas esperadas.');
        }

        foreach ($rows as $rowNumber => $row) {
            $values = array_map(fn ($value) => trim((string) $value), array_pad($row, 5, null));
            [$codigo, $descripcion, $unidadConteo, $espacioCoordinacion, $poblacionDirigida] = array_slice($values, 0, 5);

            if ($codigo === '' && $descripcion === '') {
                continue;
            }

            $excelRow = $rowNumber + 2;
            $this->validateRow($excelRow, $codigo, $descripcion, $unidadConteo, $espacioCoordinacion, $poblacionDirigida);

            Indicador::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'descripcion' => $descripcion,
                    'unidad_conteo' => $unidadConteo,
                    'espacio_coordinacion' => $espacioCoordinacion,
                    'poblacion_dirigida' => $poblacionDirigida,
                ]
            );
        }
    }

    private function validateRow(
        int $row,
        string $codigo,
        string $descripcion,
        string $unidadConteo,
        string $espacioCoordinacion,
        string $poblacionDirigida
    ): void {
        if ($codigo === '' || $descripcion === '' || $unidadConteo === '') {
            throw new RuntimeException("La fila {$row} tiene campos obligatorios vacíos.");
        }

        if (! in_array($espacioCoordinacion, Indicador::ESPACIOS_COORDINACION, true)) {
            throw new RuntimeException("La fila {$row} contiene un espacio de coordinación inválido: {$espacioCoordinacion}.");
        }

        if (! in_array($poblacionDirigida, Indicador::POBLACIONES_DIRIGIDAS, true)) {
            throw new RuntimeException("La fila {$row} contiene una población dirigida inválida: {$poblacionDirigida}.");
        }
    }
}
