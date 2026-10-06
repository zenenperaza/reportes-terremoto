<?php

namespace Tests\Feature;

use App\Models\Donante;
use App\Models\Indicador;
use App\Models\IndicadorProyecto;
use App\Models\IndicatorGroup;
use App\Models\Municipality;
use App\Models\Parish;
use App\Models\Proyecto;
use App\Models\Report;
use App\Models\Sector;
use App\Models\SectorProyecto;
use App\Models\State;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportSummaryExcelExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private array $geography;

    private IndicadorProyecto $assignment;

    private IndicadorProyecto $secondAssignment;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::create(['key' => SystemSetting::CURRENT_PERIOD, 'value' => '2026-09']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        foreach (['A', 'B'] as $code) {
            $state = State::create(['code' => $code, 'name' => 'Estado '.$code]);
            $municipality = Municipality::create(['state_id' => $state->id, 'code' => $code.'01', 'name' => 'Municipio '.$code]);
            $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => $code.'0101', 'name' => 'Parroquia '.$code]);
            $this->geography[$code] = ['state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id];
        }
        $sector = Sector::create(['codigo' => 'CP', 'name' => 'Protección', 'slug' => 'proteccion', 'estatus' => true]);
        $donor = Donante::create(['nombre' => 'Donante de prueba', 'estatus' => true]);
        $project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PR-1', 'descripcion' => 'Proyecto de prueba', 'estatus' => true]);
        $projectSector = SectorProyecto::create(['proyecto_id' => $project->id, 'sector_id' => $sector->id]);
        $group = IndicatorGroup::create(['name' => 'Apoyo psicosocial', 'description' => 'Grupo de prueba', 'sort_order' => 1]);
        foreach ([1, 2] as $number) {
            $indicator = Indicador::create(['codigo' => 'IND-'.$number, 'nombre_corto' => 'Indicador '.$number, 'descripcion' => 'Descripción '.$number,
                'indicator_group_id' => $group->id, 'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120,
                'excluir_reporte_beneficiarios' => $number === 2]);
            $assignment = IndicadorProyecto::create(['proyecto_id' => $project->id, 'sector_proyecto_id' => $projectSector->id, 'indicador_id' => $indicator->id, 'estatus' => true]);
            if ($number === 1) {
                $this->assignment = $assignment;
            } else {
                $this->secondAssignment = $assignment;
            }
        }
        $this->actingAs($this->admin);
    }

    public static function reports(): array
    {
        return [['general-reports', false], ['indicator-reports', true]];
    }

    #[DataProvider('reports')]
    public function test_excel_matches_filtered_screen_including_boundary_dates_and_legacy_records(string $route, bool $byIndicators): void
    {
        $this->fixture();
        $params = ['reported' => '', 'reporting_period' => [''], 'attention_from' => '2026-09-01', 'attention_to' => '2026-09-30'];
        $page = $this->get(route($route.'.index', $params))->assertOk()->assertSee('Exportar a Excel');
        $data = $page->viewData('summary');
        $this->assertSame(6, $data['beneficiaries']);
        $this->assertStringContainsString(e(route($route.'.export', $page->viewData('filters'))), $page->getContent());
        $response = $this->get(route($route.'.export', $params))->assertOk()->assertDownload()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $workbook = $this->load($response);
        try {
            $summary = $workbook->getSheetByName('Resumen');
            $this->assertSame(6, $summary->getCell('B3')->getValue());
            $this->assertSame(DataType::TYPE_NUMERIC, $summary->getCell('B3')->getDataType());
            foreach (['women_under_18', 'men_under_18', 'women_adults', 'men_adults'] as $index => $key) {
                $this->assertSame($data[$key], $summary->getCell('B'.($index + 4))->getValue());
            }
            $this->assertSame('Todos', $summary->getCell('B13')->getValue());
            $this->assertSame('Todos los períodos', $summary->getCell('B14')->getValue());
            $this->assertSame('01/09/2026', $summary->getCell('B15')->getValue());
            $this->assertSame('30/09/2026', $summary->getCell('B16')->getValue());
            if ($byIndicators) {
                $this->assertSame(['Resumen', 'Grupos', 'Indicadores'], $workbook->getSheetNames());
                $groups = $workbook->getSheetByName('Grupos');
                $this->assertSame(5, $groups->getCell('F3')->getValue());
                $rows = $groups->toArray();
                $legacy = collect($rows)->first(fn ($row) => $row[0] === 'Sin indicador de proyecto disponible');
                $this->assertSame(1, (int) $legacy[1]);
                $indicators = $workbook->getSheetByName('Indicadores');
                $this->assertSame('IND-1', $indicators->getCell('B3')->getValue());
                $this->assertSame(3, $indicators->getCell('J3')->getValue());
                $this->assertSame('IND-2', $indicators->getCell('B4')->getValue());
                $this->assertSame(2, $indicators->getCell('J4')->getValue());
                $this->assertSame('A2:J4', $indicators->getAutoFilter()->getRange());
            } else {
                $this->assertSame(['Resumen', 'Desgloses'], $workbook->getSheetNames());
                $sheet = $workbook->getSheetByName('Desgloses');
                $rows = $sheet->toArray();
                $this->assertContains('Beneficiarios por grupo etario y sexo', array_column($rows, 0));
                $this->assertContains('Tipo de atención', array_column($rows, 0));
                $trendIndex = array_search('Evolución de atenciones', array_column($rows, 0), true);
                $this->assertSame('2026-09-01', Date::excelToDateTimeObject($sheet->getCell('A'.($trendIndex + 3))->getValue())->format('Y-m-d'));
                $this->assertSame('dd/mm/yyyy', $sheet->getCell('A'.($trendIndex + 3))->getStyle()->getNumberFormat()->getFormatCode());
                $trendTotal = array_sum(array_column(array_slice($rows, $trendIndex + 2), 3));
                $this->assertSame(6, $trendTotal);
            }
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    #[DataProvider('reports')]
    public function test_export_reuses_status_period_location_indicator_and_person_filters(string $route, bool $byIndicators): void
    {
        $this->fixture();
        $base = ['reported' => '', 'reporting_period' => '', 'attention_from' => '2026-09-01', 'attention_to' => '2026-09-30'];
        $cases = [
            [['reported' => '1', 'attention_from' => '2026-09-30', 'attention_to' => ''], 2],
            [['reported' => '0'], 4],
            [['reporting_period' => ['2026-09']], 5],
            [['reporting_period' => ['unassigned'], 'attention_from' => '2026-09-15', 'attention_to' => ''], 1],
            [['reporting_period' => ['2026-09', 'unassigned']], 6],
            [['state_id' => [$this->geography['A']['state_id']]], 4],
            [['state_id' => [$this->geography['A']['state_id'], $this->geography['B']['state_id']]], 6],
            [['indicador_id' => [$this->secondAssignment->indicador_id]], 2],
            [['sex' => 'Mujer', 'age_from' => 18, 'age_to' => 59], 2],
            [['age_group' => '0-5'], 1],
            [['registered_from' => '2026-09-10'], 6],
            [['is_recurrent' => '1'], 1],
        ];
        foreach ($cases as [$filters, $expected]) {
            $params = array_replace($base, $filters);
            $this->get(route($route.'.index', $params))->assertOk()->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === $expected);
            $workbook = $this->load($this->get(route($route.'.export', $params))->assertOk());
            try {
                $this->assertSame($expected, $workbook->getSheetByName('Resumen')->getCell('B3')->getValue(), json_encode($filters));
            } finally {
                $workbook->disconnectWorksheets();
            }
        }
        $default = $this->load($this->get(route($route.'.export', ['reported' => '']))->assertOk());
        try {
            $this->assertSame(8, $default->getSheetByName('Resumen')->getCell('B3')->getValue());
            $this->assertSame('Todos los períodos', $default->getSheetByName('Resumen')->getCell('B14')->getValue());
        } finally {
            $default->disconnectWorksheets();
        }
    }

    #[DataProvider('reports')]
    public function test_permissions_and_group_visibility_apply_to_excel(string $route, bool $byIndicators): void
    {
        $restrictedRole = Role::findOrCreate('excel-reviewer', 'web');
        $viewer = User::factory()->create(['role' => $restrictedRole->name, 'countrywide_access' => true]);
        $this->record($viewer, 'A', '2026-09-01', '2026-09', $this->assignment, [['age' => 10, 'sex' => 'Mujer']]);
        $this->actingAs($viewer)->get(route($route.'.index'))->assertOk()->assertDontSee('Exportar a Excel');
        $this->get(route($route.'.export'))->assertForbidden();
        $viewer->givePermissionTo('exportar registros excel');
        $this->actingAs($viewer->fresh())->get(route($route.'.index'))->assertSee('Exportar a Excel');
        $this->get(route($route.'.export'))->assertOk();

        $group = UserGroup::create(['name' => 'Equipo visible', 'is_active' => true]);
        $coordinator = User::factory()->create(['role' => 'coordinator']);
        $coordinator->givePermissionTo('exportar registros excel');
        $partner = User::factory()->create(['role' => 'reporter']);
        $coordinator->userGroups()->attach($group);
        $partner->userGroups()->attach($group);
        $this->record($partner, 'A', '2026-09-01', '2026-09', $this->assignment, [['age' => 20, 'sex' => 'Mujer']]);
        $this->record($this->admin, 'B', '2026-09-01', '2026-09', $this->secondAssignment, [['age' => 30, 'sex' => 'Hombre']]);
        $workbook = $this->load($this->actingAs($coordinator)->get(route($route.'.export'))->assertOk());
        try {
            $this->assertSame(1, $workbook->getSheetByName('Resumen')->getCell('B3')->getValue());
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    #[DataProvider('reports')]
    public function test_empty_results_and_invalid_filters(string $route, bool $byIndicators): void
    {
        $workbook = $this->load($this->get(route($route.'.export'))->assertOk());
        try {
            $this->assertSame(0, $workbook->getSheetByName('Resumen')->getCell('B3')->getValue());
        } finally {
            $workbook->disconnectWorksheets();
        }
        $this->fixture();
        $this->getJson(route($route.'.export', ['attention_from' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('attention_from');
        $this->getJson(route($route.'.export', ['attention_from' => '2026-09-30', 'attention_to' => '2026-09-01']))->assertUnprocessable()->assertJsonValidationErrors('attention_to');
        $this->getJson(route($route.'.export', ['reported' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('reported');
        $this->getJson(route($route.'.export', ['reporting_period' => ['2026-99']]))->assertUnprocessable()->assertJsonValidationErrors('reporting_period');
    }

    public function test_catalog_text_cannot_become_excel_formulas(): void
    {
        $this->fixture();
        $this->assignment->indicador->update(['codigo' => '=1+1', 'nombre_corto' => '+SUM(1,2)']);
        $workbook = $this->load($this->get(route('indicator-reports.export'))->assertOk());
        try {
            $sheet = $workbook->getSheetByName('Indicadores');
            $this->assertSame('=1+1', $sheet->getCell('B3')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('B3')->getDataType());
            $this->assertSame('+SUM(1,2)', $sheet->getCell('C3')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C3')->getDataType());
        } finally {
            $workbook->disconnectWorksheets();
        }
    }

    private function fixture(): void
    {
        $this->record($this->admin, 'A', '2026-09-01', '2026-09', $this->assignment, [
            ['age' => 4, 'sex' => 'Mujer'], ['age' => 10, 'sex' => 'Hombre'], ['age' => 35, 'sex' => 'Mujer', 'is_recurrent' => true],
        ]);
        $last = $this->record($this->admin, 'B', '2026-09-30', '2026-09', $this->secondAssignment, [
            ['age' => 20, 'sex' => 'Mujer'], ['age' => 30, 'sex' => 'Hombre'],
        ]);
        $last->beneficiaries()->update(['reported_at' => '2026-10-01', 'reported' => true]);
        $this->record($this->admin, 'A', '2026-09-15', null, null, [['age' => 15, 'sex' => 'Hombre']]);
        $this->record($this->admin, 'A', '2026-08-31', '2026-08', $this->assignment, [['age' => 12, 'sex' => 'Mujer']]);
        $this->record($this->admin, 'B', '2026-10-01', '2026-10', $this->assignment, [['age' => 12, 'sex' => 'Mujer']]);
    }

    private function record(User $owner, string $location, string $date, ?string $period, ?IndicadorProyecto $assignment, array $people): Report
    {
        $report = Report::create($this->geography[$location] + [
            'reporting_period' => $period, 'user_id' => $owner->id, 'proyecto_id' => $assignment?->proyecto_id,
            'indicador_proyecto_id' => $assignment?->id, 'sector_id' => $assignment?->asignacionSector?->sector_id,
            'report_date' => $date, 'reporter_first_name' => 'Prueba', 'reporter_last_name' => 'Excel', 'reporter_email' => $owner->email, 'organization' => 'ASONACOP',
            'installation_type' => 'Comunidad / Espacio Comunitario', 'place_name' => 'Lugar '.$location,
            'recurrence_status' => 'nuevo', 'total_beneficiaries' => count($people), 'beneficiary_breakdown' => [],
        ]);
        foreach ($people as $person) {
            $beneficiary = $report->beneficiaries()->create($person + ['full_name' => 'PRUEBA CONFIDENCIAL', 'has_informed_consent' => true]);
            $beneficiary->forceFill(['created_at' => '2026-09-10 12:00:00'])->save();
        }

        return $report;
    }

    private function load(TestResponse $response): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'sia-summary-export-');
        try {
            file_put_contents($path, $response->streamedContent());
            $workbook = IOFactory::load($path);
            foreach ($workbook->getAllSheets() as $sheet) {
                $this->assertStringNotContainsString('PRUEBA CONFIDENCIAL', json_encode($sheet->toArray()));
            }

            return $workbook;
        } finally {
            @unlink($path);
        }
    }
}
