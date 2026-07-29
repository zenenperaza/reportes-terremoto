<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

return new class extends Migration
{
    public function up(): void
    {
        $path = database_path('reference/parroquias-coordenadas.xlsx');
        if (! file_exists($path)) {
            throw new \RuntimeException("No se encontró el catálogo de coordenadas parroquiales: {$path}");
        }

        $sheet = IOFactory::load($path)->getActiveSheet();
        $expectedHeaders = [
            'A1' => 'Codigo_Estado',
            'C1' => 'Codigo_Municipio',
            'F1' => 'Codigo_Parroquia',
            'H1' => 'latitud',
            'I1' => 'longitud',
        ];
        foreach ($expectedHeaders as $cell => $header) {
            if (trim((string) $sheet->getCell($cell)->getValue()) !== $header) {
                throw new \RuntimeException("El catálogo de coordenadas no contiene la columna esperada {$header}.");
            }
        }

        if (DB::table('parishes')->doesntExist()) {
            return;
        }

        DB::transaction(function () use ($sheet): void {
            for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
                $stateCode = trim((string) $sheet->getCell("A{$row}")->getValue());
                $municipalityCode = trim((string) $sheet->getCell("C{$row}")->getValue());
                $parishCode = trim((string) $sheet->getCell("F{$row}")->getValue());
                $latitude = $sheet->getCell("H{$row}")->getValue();
                $longitude = $sheet->getCell("I{$row}")->getValue();

                if ($parishCode === '' || ! is_numeric($latitude) || ! is_numeric($longitude)) {
                    throw new \RuntimeException("Coordenadas parroquiales incompletas en la fila {$row}.");
                }

                DB::table('parishes')
                    ->join('municipalities', 'parishes.municipality_id', '=', 'municipalities.id')
                    ->join('states', 'municipalities.state_id', '=', 'states.id')
                    ->where('parishes.code', $parishCode)
                    ->where('municipalities.code', $municipalityCode)
                    ->where('states.code', $stateCode)
                    ->update([
                        'parishes.latitude' => round((float) $latitude, 7),
                        'parishes.longitude' => round((float) $longitude, 7),
                    ]);
            }
        });
    }

    public function down(): void
    {
        DB::table('parishes')->update(['latitude' => null, 'longitude' => null]);
    }
};
