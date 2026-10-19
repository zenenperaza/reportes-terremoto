<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Donante;
use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Proyecto;
use App\Models\Report;
use App\Models\Sector;
use App\Models\SectorProyecto;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

class BeneficiaryExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_separates_project_code_and_sector_and_includes_activity_location_details(): void
    {
        $administrator = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $state = State::create(['code' => 'VE24', 'name' => 'La Guaira']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE2401', 'name' => 'Vargas']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE240101', 'name' => 'Caraballeda']);
        $sector = Sector::create(['codigo' => 'CP', 'name' => 'Protección', 'slug' => 'proteccion', 'sort_order' => 1]);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'ACT-01', 'title' => 'Atención', 'sort_order' => 1]);
        $donor = Donante::create(['nombre' => 'UNICEF', 'estatus' => true]);
        $project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PROY-01', 'descripcion' => 'Proyecto de prueba', 'estatus' => true]);
        $sectorAssignment = SectorProyecto::create(['proyecto_id' => $project->id, 'sector_id' => $sector->id]);
        $indicator = Indicador::create([
            'codigo' => 'GCLPR/SCA12/IC1/IE1',
            'nombre_corto' => 'NNA en actividades grupales',
            'descripcion' => 'NNA - Número de personas atendidas',
            'unidad_conteo' => 'Personas',
            'espacio_coordinacion' => 'NNA',
            'edad_desde' => 0,
            'edad_hasta' => 17,
        ]);
        $indicatorAssignment = IndicadorProyecto::create([
            'proyecto_id' => $project->id,
            'sector_proyecto_id' => $sectorAssignment->id,
            'indicador_id' => $indicator->id,
            'estatus' => true,
        ]);
        $report = Report::create([
            'reporting_period' => '2026-10',
            'user_id' => $administrator->id,
            'proyecto_id' => $project->id,
            'indicador_proyecto_id' => $indicatorAssignment->id,
            'report_date' => '2026-09-15',
            'reporter_first_name' => 'Administrador',
            'reporter_last_name' => 'ASONACOP',
            'reporter_email' => $administrator->email,
            'organization' => 'ASONACOP',
            'state_id' => $state->id,
            'municipality_id' => $municipality->id,
            'parish_id' => $parish->id,
            'installation_type' => 'Comunidad / Espacio Comunitario',
            'place_name' => 'Centro comunitario',
            'activity_details' => 'Entrega de kits y orientación',
            'sector_id' => $sector->id,
            'activity_id' => $activity->id,
            'recurrence_status' => 'nuevo',
            'total_beneficiaries' => 1,
            'beneficiary_breakdown' => [],
        ]);
        $report->beneficiaries()->create([
            'has_informed_consent' => true,
            'age' => 12,
            'sex' => 'Mujer',
            'disability' => 'Ninguna',
            'ethnicity' => 'Ninguna',
            'pregnant_lactating' => 'N/A',
            'is_recurrent' => false,
        ]);

        $response = $this->actingAs($administrator)->get(route('beneficiaries.export', ['reported' => '']));
        $response->assertOk()->assertDownload();

        $path = tempnam(sys_get_temp_dir(), 'beneficiary-indicator-export-');
        try {
            file_put_contents($path, $response->streamedContent());
            $worksheet = IOFactory::load($path)->getActiveSheet();

            $this->assertSame('Nombre específico del lugar', $worksheet->getCell('F1')->getValue());
            $this->assertSame('Código del proyecto', $worksheet->getCell('I1')->getValue());
            $this->assertSame('Sector programático', $worksheet->getCell('J1')->getValue());
            $this->assertSame('Código del indicador', $worksheet->getCell('K1')->getValue());
            $this->assertSame('Indicador a reportar', $worksheet->getCell('L1')->getValue());
            $this->assertSame('Detalles adicionales de la actividad', $worksheet->getCell('M1')->getValue());
            $this->assertSame('Centro comunitario', $worksheet->getCell('F2')->getValue());
            $this->assertSame('PROY-01', $worksheet->getCell('I2')->getValue());
            $this->assertSame('Protección', $worksheet->getCell('J2')->getValue());
            $this->assertSame('GCLPR/SCA12/IC1/IE1', $worksheet->getCell('K2')->getValue());
            $this->assertSame('NNA - Número de personas atendidas', $worksheet->getCell('L2')->getValue());
            $this->assertSame('Entrega de kits y orientación', $worksheet->getCell('M2')->getValue());
            $this->assertSame('Período', $worksheet->getCell('X1')->getValue());
            $this->assertSame('Octubre 2026', $worksheet->getCell('X2')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $worksheet->getCell('X2')->getDataType());
            $this->assertSame('A1:X1', $worksheet->getAutoFilter()->getRange());
            $this->assertSame('A2', $worksheet->getFreezePane());
            $this->assertEquals(24, $worksheet->getColumnDimension('X')->getWidth());
            $this->assertSame($worksheet->getStyle('W1')->getFill()->getStartColor()->getARGB(),
                $worksheet->getStyle('X1')->getFill()->getStartColor()->getARGB());
            $this->assertSame('dd/mm/yyyy', $worksheet->getStyle('N2')->getNumberFormat()->getFormatCode());
            $this->assertSame('15/09/2026', $worksheet->getCell('N2')->getFormattedValue());
            $this->assertSame('dd/mm/yyyy', $worksheet->getStyle('U2')->getNumberFormat()->getFormatCode());
            $this->assertSame('dd/mm/yyyy hh:mm', $worksheet->getStyle('V2')->getNumberFormat()->getFormatCode());
        } finally {
            @unlink($path);
        }
    }

    public function test_export_shows_each_record_period_and_preserves_single_multiple_and_unassigned_filters(): void
    {
        $administrator = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $state = State::create(['code' => 'VE24', 'name' => 'La Guaira']);
        $municipality = Municipality::create(['state_id' => $state->id, 'code' => 'VE2401', 'name' => 'Vargas']);
        $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => 'VE240101', 'name' => 'Caraballeda']);
        $sector = Sector::create(['codigo' => 'CP', 'name' => 'Protección', 'slug' => 'proteccion', 'sort_order' => 1]);
        $activity = Activity::create(['sector_id' => $sector->id, 'code' => 'ACT-01', 'title' => 'Atención', 'sort_order' => 1]);
        $expected = [];
        foreach (['2026-09' => 'Septiembre 2026', '2026-10' => 'Octubre 2026', 'unassigned' => 'Sin período asignado'] as $period => $label) {
            $report = Report::create([
                'reporting_period' => $period === 'unassigned' ? null : $period,
                'user_id' => $administrator->id, 'report_date' => '2026-09-15',
                'reporter_first_name' => 'Administrador', 'reporter_last_name' => 'ASONACOP',
                'reporter_email' => $administrator->email, 'organization' => 'ASONACOP',
                'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id,
                'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Centro comunitario',
                'sector_id' => $sector->id, 'activity_id' => $activity->id,
                'recurrence_status' => 'nuevo', 'total_beneficiaries' => 1, 'beneficiary_breakdown' => [],
            ]);
            $beneficiary = $report->beneficiaries()->create([
                'has_informed_consent' => true, 'age' => 12, 'sex' => 'Mujer', 'disability' => 'Ninguna',
                'ethnicity' => 'Ninguna', 'pregnant_lactating' => 'N/A', 'is_recurrent' => false,
            ]);
            $expected[$period] = [$beneficiary->id => $label];
        }

        $this->actingAs($administrator);
        foreach ([
            ['', $expected['2026-09'] + $expected['2026-10'] + $expected['unassigned']],
            ['2026-10', $expected['2026-10']],
            [['2026-09', '2026-10'], $expected['2026-09'] + $expected['2026-10']],
            [['2026-10', 'unassigned'], $expected['2026-10'] + $expected['unassigned']],
            ['unassigned', $expected['unassigned']],
            ['2026-08', []],
        ] as [$filter, $expectedRows]) {
            $worksheet = $this->exportWorksheet(['reporting_period' => $filter, 'reported' => '']);
            $this->assertSame('Período', $worksheet->getCell('X1')->getValue());
            $rows = [];
            for ($row = 2; $row <= $worksheet->getHighestDataRow(); $row++) {
                $id = $worksheet->getCell('A'.$row)->getValue();
                if ($id !== null) {
                    $rows[(int) $id] = $worksheet->getCell('X'.$row)->getValue();
                }
            }
            ksort($rows);
            ksort($expectedRows);
            $this->assertSame($expectedRows, $rows);
        }
    }

    private function exportWorksheet(array $filters): Worksheet
    {
        $response = $this->get(route('beneficiaries.export', $filters))->assertOk()->assertDownload();
        $path = tempnam(sys_get_temp_dir(), 'beneficiary-period-export-');
        try {
            file_put_contents($path, $response->streamedContent());

            return IOFactory::load($path)->getActiveSheet();
        } finally {
            @unlink($path);
        }
    }
}
