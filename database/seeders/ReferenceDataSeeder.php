<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Sector;
use App\Models\State;
use Illuminate\Database\Seeder;
use RuntimeException;
use ZipArchive;

class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $sheets = $this->readReferenceWorkbook(database_path('reference/unicef-terremoto-referencia.xlsx'));
        $locations = $sheets['Ubicaciones'];

        $states = [];
        for ($row = 72; $row <= 1206; $row++) {
            $stateCode = $locations['K'.$row] ?? null;
            $stateName = $locations['L'.$row] ?? null;
            if ($stateCode && $stateName) {
                $states[$stateCode] = ['code' => $stateCode, 'name' => $stateName];
            }
        }

        foreach ($states as $state) {
            State::updateOrCreate(['code' => $state['code']], $state);
        }
        $stateIds = State::pluck('id', 'code')->all();
        $municipalityIds = [];

        for ($row = 72; $row <= 1206; $row++) {
            $stateCode = $locations['K'.$row] ?? null;
            $municipalityCode = $locations['M'.$row] ?? null;
            $municipalityName = $locations['N'.$row] ?? null;
            if (! $stateCode || ! $municipalityCode || ! $municipalityName) {
                continue;
            }

            $municipality = Municipality::updateOrCreate(
                ['code' => $municipalityCode],
                ['state_id' => $stateIds[$stateCode], 'name' => $municipalityName],
            );
            $municipalityIds[$municipalityCode] = $municipality->id;
        }

        for ($row = 72; $row <= 1206; $row++) {
            $municipalityCode = $locations['M'.$row] ?? null;
            $parishCode = $locations['O'.$row] ?? null;
            $parishName = $locations['P'.$row] ?? null;
            if (! $municipalityCode || ! $parishCode || ! $parishName) {
                continue;
            }

            Parish::updateOrCreate(
                ['code' => $parishCode],
                ['municipality_id' => $municipalityIds[$municipalityCode], 'name' => $parishName],
            );
        }

        $this->seedProgrammaticCatalogues($sheets['Listas']);
    }

    private function seedProgrammaticCatalogues(array $lists): void
    {
        foreach (config('reports.sectors') as $index => $sectorData) {
            $sector = Sector::updateOrCreate(
                ['slug' => $sectorData['slug']],
                ['name' => $sectorData['name'], 'sort_order' => $index + 1],
            );

            Activity::updateOrCreate(
                ['sector_id' => $sector->id, 'code' => 'OTHER'],
                ['title' => 'Otra actividad del sector - especifique en los detalles', 'sort_order' => 999, 'active' => true],
            );
        }

        $protection = Sector::where('slug', 'proteccion-ninez')->firstOrFail();
        $sortOrder = 1;
        for ($row = 6; $row <= 25; $row++) {
            $title = trim((string) ($lists['AH'.$row] ?? ''));
            if ($title === '') {
                continue;
            }
            preg_match('/^([^:]+):/', $title, $matches);
            Activity::updateOrCreate(
                ['sector_id' => $protection->id, 'code' => $matches[1] ?? 'PN-'.$row],
                ['title' => $title, 'sort_order' => $sortOrder++, 'active' => true],
            );
        }

        $sbc = Sector::where('slug', 'sbc')->firstOrFail();
        foreach ([
            'CCA4: GEN.01: Participación de personas de la comunidad y otros actores clave en análisis de género que informan a la programación humanitaria.',
            'CCA5: GEN.02: Difusión comunitaria de mensajes, sensibilización y fortalecimiento de capacidades a puntos focales, organizaciones de la sociedad civil o la institucionalidad en igualdad de género, inclusión de personas LGBTIQ+, empoderamiento de las mujeres y las niñas.',
        ] as $index => $title) {
            Activity::updateOrCreate(
                ['sector_id' => $sbc->id, 'code' => 'GEN.0'.($index + 1)],
                ['title' => $title, 'sort_order' => $index + 1, 'active' => true],
            );
        }
    }

    /**
     * Reads only the cells required by the source workbook, avoiding an additional runtime package.
     *
     * @return array<string, array<string, string>>
     */
    private function readReferenceWorkbook(string $path): array
    {
        if (! file_exists($path)) {
            throw new RuntimeException("No se encontró el archivo de referencia: {$path}");
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo Excel de referencia.');
        }

        try {
            $sharedStrings = $this->sharedStrings((string) $zip->getFromName('xl/sharedStrings.xml'));

            return [
                'Listas' => $this->sheetCells((string) $zip->getFromName('xl/worksheets/sheet1.xml'), $sharedStrings),
                'Ubicaciones' => $this->sheetCells((string) $zip->getFromName('xl/worksheets/sheet2.xml'), $sharedStrings),
            ];
        } finally {
            $zip->close();
        }
    }

    /** @return array<int, string> */
    private function sharedStrings(string $xml): array
    {
        $document = simplexml_load_string($xml);
        $document->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $strings = [];
        foreach ($document->xpath('//main:si') as $item) {
            $item->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $strings[] = implode('', array_map('strval', $item->xpath('.//main:t')));
        }

        return $strings;
    }

    /** @param array<int, string> $sharedStrings  @return array<string, string> */
    private function sheetCells(string $xml, array $sharedStrings): array
    {
        $document = simplexml_load_string($xml);
        $document->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $cells = [];

        foreach ($document->xpath('//main:sheetData/main:row/main:c') as $cell) {
            $cell->registerXPathNamespace('main', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            $reference = (string) $cell['r'];
            $type = (string) $cell['t'];
            $value = (string) ($cell->xpath('./main:v')[0] ?? '');

            if ($type === 's') {
                $value = $sharedStrings[(int) $value] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = implode('', array_map('strval', $cell->xpath('.//main:t')));
            }

            if ($reference !== '' && $value !== '') {
                $cells[$reference] = $value;
            }
        }

        return $cells;
    }
}
