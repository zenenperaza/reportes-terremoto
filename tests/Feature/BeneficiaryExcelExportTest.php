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
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class BeneficiaryExcelExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_places_indicator_code_between_sector_and_indicator_name(): void
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

            $this->assertSame('Sector programático', $worksheet->getCell('I1')->getValue());
            $this->assertSame('Código del indicador', $worksheet->getCell('J1')->getValue());
            $this->assertSame('Indicador a reportar', $worksheet->getCell('K1')->getValue());
            $this->assertSame('PROY-01', $worksheet->getCell('I2')->getValue());
            $this->assertSame('GCLPR/SCA12/IC1/IE1', $worksheet->getCell('J2')->getValue());
            $this->assertSame('NNA - Número de personas atendidas', $worksheet->getCell('K2')->getValue());
        } finally {
            @unlink($path);
        }
    }
}
