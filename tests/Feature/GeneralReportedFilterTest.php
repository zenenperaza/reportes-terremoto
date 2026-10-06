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
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GeneralReportedFilterTest extends TestCase
{
    use RefreshDatabase;

    private array $records = [];

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::create(['key' => SystemSetting::CURRENT_PERIOD, 'value' => '2026-09']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $donor = Donante::create(['nombre' => 'Donante', 'estatus' => true]);
        $project = Proyecto::create(['donante_id' => $donor->id, 'codigo' => 'PROY', 'descripcion' => 'Proyecto', 'estatus' => true]);
        $group = IndicatorGroup::create(['name' => 'Apoyo psicosocial', 'description' => 'Descripción del grupo', 'sort_order' => 1]);
        $excludedGroup = IndicatorGroup::create(['name' => 'Autocuidado', 'description' => 'Incluido en estos dos informes', 'sort_order' => 6]);
        foreach (['A', 'B', 'C'] as $code) {
            $state = State::create(['code' => $code, 'name' => 'Estado '.$code]);
            $municipality = Municipality::create(['state_id' => $state->id, 'code' => $code.'01', 'name' => 'Municipio '.$code]);
            $parish = Parish::create(['municipality_id' => $municipality->id, 'code' => $code.'0101', 'name' => 'Parroquia '.$code]);
            $sector = Sector::create(['name' => 'Sector '.$code, 'slug' => 'sector-'.strtolower($code), 'estatus' => $code !== 'C']);
            $assignedSector = SectorProyecto::create(['sector_id' => $sector->id, 'proyecto_id' => $project->id]);
            $indicator = Indicador::create(['codigo' => 'IND-'.$code, 'descripcion' => 'Indicador '.$code, 'indicator_group_id' => $code === 'B' ? $excludedGroup->id : $group->id,
                'unidad_conteo' => 'Personas', 'espacio_coordinacion' => 'NNA', 'edad_desde' => 0, 'edad_hasta' => 120,
                'excluir_reporte_beneficiarios' => $code === 'B']);
            $assignment = IndicadorProyecto::create(['indicador_id' => $indicator->id, 'proyecto_id' => $project->id,
                'sector_proyecto_id' => $assignedSector->id, 'estatus' => $code !== 'C']);
            $type = config('reports.installation_types')[$code === 'A' ? 0 : 1];
            $report = Report::create(['user_id' => $admin->id, 'proyecto_id' => $project->id, 'indicador_proyecto_id' => $assignment->id,
                'reporting_period' => $code === 'A' ? '2026-08' : '2026-09', 'report_date' => $code === 'A' ? '2026-08-05' : ($code === 'B' ? '2026-09-15' : '2026-09-20'),
                'state_id' => $state->id, 'municipality_id' => $municipality->id, 'parish_id' => $parish->id, 'sector_id' => $sector->id,
                'reporter_first_name' => 'Prueba', 'reporter_last_name' => 'Estado', 'reporter_email' => $admin->email,
                'organization' => 'ASONACOP', 'installation_type' => $type, 'place_name' => 'Lugar '.$code,
                'recurrence_status' => 'nuevo', 'total_beneficiaries' => $code === 'C' ? 2 : 1, 'beneficiary_breakdown' => []]);
            $people = match ($code) {
                'A' => [[10, 'Mujer', false, '2026-08-08']],
                'B' => [[35, 'Hombre', true, '2026-09-16']],
                'C' => [[16, 'Mujer', false, '2026-09-21'], [65, 'Hombre', true, '2026-09-22']],
            };
            foreach ($people as [$age, $sex, $reported, $created]) {
                $person = $report->beneficiaries()->create(['full_name' => 'Prueba', 'age' => $age, 'sex' => $sex,
                    'is_recurrent' => $reported, 'reported' => $reported, 'reported_at' => $reported ? '2026-10-02' : null]);
                $person->forceFill(['created_at' => $created.' 12:00:00', 'reported' => $reported, 'reported_at' => $reported ? '2026-10-02' : null])->save();
            }
            $this->records[$code] = compact('report', 'state', 'municipality', 'parish', 'sector', 'indicator', 'type');
        }
    }

    public static function reports(): array
    {
        return [['general-reports'], ['indicator-reports']];
    }

    #[DataProvider('reports')]
    public function test_checkbox_picker_has_groups_and_includes_beneficiary_excluded_indicators(string $route): void
    {
        $response = $this->get(route($route.'.index', ['reported' => '', 'indicador_id' => ['']]))->assertOk()
            ->assertViewHas('filters', fn ($filters) => $filters['indicador_id'] === [])
            ->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 4)
            ->assertSee('Seleccione indicador')->assertSee('Seleccionar todos los grupos')->assertSee('Seleccionar todo el grupo')
            ->assertSee('Todos los indicadores (sin filtro)')->assertSee('1. Apoyo psicosocial')->assertSee('6. Autocuidado')
            ->assertSee('Descripción del grupo')->assertSee('Edad: 0 a 120 años');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $root = '//*[@id="general-indicator-picker"]';
        $this->assertSame(3, $xpath->query($root.'//input[@type="checkbox" and @name="indicador_id[]"]')->length);
        $this->assertSame(1, $xpath->query($root.'//input[@type="hidden" and @name="indicador_id[]" and @value=""]')->length);
        $this->assertSame(0, $xpath->query('//select[@name="indicador_id[]"]')->length);
        $this->assertSame(2, $xpath->query($root.'//section[@data-indicator-group]')->length);
        $this->assertSame(1, $xpath->query($root.'//input[@type="checkbox" and @name="indicador_id[]" and @value="'.$this->records['B']['indicator']->id.'"]')->length);
        $this->assertSame('false', $xpath->query('//*[@id="general-indicator-toggle"]')->item(0)->getAttribute('aria-expanded'));
    }

    #[DataProvider('reports')]
    public function test_flagged_indicator_selection_respects_other_filters_and_excel_without_changing_beneficiary_exclusion(string $route): void
    {
        $record = $this->records['B'];
        $params = ['reported' => '1', 'reporting_period' => ['2026-09'], 'indicador_id' => ['', (string) $record['indicator']->id],
            'attention_from' => '2026-09-15', 'attention_to' => '2026-09-20', 'state_id' => [$record['state']->id], 'sex' => 'Hombre', 'age_from' => 18, 'age_to' => 59];
        $this->get(route($route.'.index', $params))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 1)
            ->assertSee('name="indicador_id[]" value="'.$record['indicator']->id.'" checked', false);
        $response = $this->get(route($route.'.export', $params))->assertOk()->assertDownload();
        $path = tempnam(sys_get_temp_dir(), 'sia-picker-export-');
        try {
            file_put_contents($path, $response->streamedContent());
            $book = IOFactory::load($path);
            $this->assertSame(1, $book->getSheetByName('Resumen')->getCell('B3')->getValue());
            if ($route === 'indicator-reports') {
                $this->assertSame('IND-B', $book->getSheetByName('Indicadores')->getCell('B3')->getValue());
                $this->assertSame(1, $book->getSheetByName('Indicadores')->getCell('J3')->getValue());
            }
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->get(route('beneficiaries.summary', ['reported' => '1', 'reporting_period' => ['2026-09'],
            'indicador_proyecto_id' => $record['report']->indicador_proyecto_id]))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total'] === 0);
    }

    #[DataProvider('reports')]
    public function test_one_catalog_indicator_across_multiple_sector_assignments_has_one_selectable_card(string $route): void
    {
        $first = $this->records['A'];
        $second = $this->records['B'];
        $project = Proyecto::findOrFail($second['report']->proyecto_id)->replicate();
        $project->fill(['codigo' => 'SEGUNDO'])->save();
        $sector = SectorProyecto::create(['sector_id' => $second['sector']->id, 'proyecto_id' => $project->id]);
        $assignment = IndicadorProyecto::create(['indicador_id' => $first['indicator']->id, 'proyecto_id' => $project->id,
            'sector_proyecto_id' => $sector->id, 'estatus' => true]);
        $report = $first['report']->replicate();
        $report->fill(['proyecto_id' => $project->id, 'indicador_proyecto_id' => $assignment->id, 'sector_id' => $second['sector']->id])->save();
        $report->beneficiaries()->create(['full_name' => 'Prueba', 'sex' => 'Mujer', 'age' => 10]);
        $response = $this->get(route($route.'.index', ['reported' => '', 'sector_id' => $second['sector']->id, 'indicador_id' => [$first['indicator']->id]]))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 1);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $boxes = $xpath->query('//*[@id="general-indicator-picker"]//input[@type="checkbox" and @name="indicador_id[]" and @value="'.$first['indicator']->id.'"]');
        $this->assertSame(1, $boxes->length);
        $this->assertTrue($boxes->item(0)->hasAttribute('checked'));
        $this->assertFalse($boxes->item(0)->hasAttribute('disabled'));
        $this->assertEqualsCanonicalizing(array_map('strval', [$first['sector']->id, $second['sector']->id]), json_decode($boxes->item(0)->parentNode->parentNode->getAttribute('data-sectors'), true));
    }

    #[DataProvider('reports')]
    public function test_all_options_follow_status_including_mixed_reports_and_historical_indicators(string $route): void
    {
        foreach (['0' => ['A', 'C'], '1' => ['B', 'C'], '' => ['A', 'B', 'C']] as $status => $codes) {
            $status = (string) $status;
            $expected = fn ($key) => collect($codes)->map(fn ($code) => $this->records[$code][$key]->id)->sort()->values()->all();
            $response = $this->get(route($route.'.index', ['reported' => $status]))->assertOk()
                ->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === ($status === '' ? 4 : 2));
            foreach (['states' => 'state', 'sectors' => 'sector', 'indicators' => 'indicator'] as $view => $key) {
                $response->assertViewHas($view, fn ($items) => $items->pluck('id')->sort()->values()->all() === $expected($key));
            }
            $response->assertViewHas('places', fn ($places) => $places->all() === array_map(fn ($code) => 'Lugar '.$code, $codes));
            $response->assertViewHas('installationTypes', fn ($types) => $types->sort()->values()->all() === collect($codes)->map(fn ($code) => $this->records[$code]['type'])->unique()->sort()->values()->all());
            $response->assertViewHas('sexOptions', fn ($sexes) => $sexes->all() === ($status === '' ? ['Hombre', 'Mujer'] : ($status === '1' ? ['Hombre'] : ['Mujer'])));
            if ($status !== '') {
                $response->assertViewHas('recurrenceOptions', fn ($values) => $values === [$status]);
                $response->assertViewHas('ageGroupOptions', fn ($groups) => array_keys($groups) === ($status === '0' ? ['6-11', '12-17'] : ['30-59', '60+']));
            }
            $this->getJson(route($route.'.locations', ['reported' => $status]))->assertOk()
                ->assertJsonCount(count($codes), 'states')->assertJsonCount(count($codes), 'municipalities')->assertJsonCount(count($codes), 'parishes');
        }
        $this->get(route($route.'.index', ['reported' => '1']))->assertViewHas('dateBounds', [
            'attention' => ['min' => '2026-09-15', 'max' => '2026-09-20'],
            'registered' => ['min' => '2026-09-16', 'max' => '2026-09-22'],
        ])->assertViewHas('periodOptions', fn ($periods) => ! $periods->has('2026-08'));
    }

    #[DataProvider('reports')]
    public function test_date_endpoint_keeps_excluded_indicators_only_in_general_and_indicator_reports(string $route): void
    {
        $params = ['reported' => '1', 'reporting_period' => ['2026-09']];
        $this->getJson(route($route.'.dates', $params))->assertOk()->assertExactJson([
            'attention' => ['min' => '2026-09-15', 'max' => '2026-09-20'],
            'registered' => ['min' => '2026-09-16', 'max' => '2026-09-22'],
        ]);
        $this->getJson(route('beneficiaries.dates', $params))->assertOk()->assertExactJson([
            'attention' => ['min' => '2026-09-20', 'max' => '2026-09-20'],
            'registered' => ['min' => '2026-09-22', 'max' => '2026-09-22'],
        ]);
    }

    #[DataProvider('reports')]
    public function test_status_has_its_own_form_only_preserves_periods_and_is_carried_by_other_filters(string $route): void
    {
        $response = $this->get(route($route.'.index', ['reported' => '0', 'reporting_period' => ['2026-08', '2026-09'], 'place_name' => 'Lugar A']))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[@id="general-reported-filter"]//select[@name="reported"]')->length);
        $this->assertSame(2, $xpath->query('//form[@id="general-reported-filter"]//input[@name="reporting_period[]"]')->length);
        $this->assertSame(0, $xpath->query('//form[@id="general-reported-filter"]//input[@name="place_name"]')->length);
        $this->assertSame(0, $xpath->query('//form[@id="general-report-filters"]//select[@name="reported"]')->length);
        $this->assertSame('0', $xpath->query('//form[@id="general-report-filters"]//input[@name="reported"]')->item(0)->getAttribute('value'));
        $response->assertSeeInOrder(['Estado de reporte', 'Aplicar estado', 'Filtros del informe', 'id="reporting-period"'], false);
        // The options are based on reported status, not the narrower place/period filters.
        $response->assertViewHas('places', fn ($places) => $places->all() === ['Lugar A', 'Lugar C']);
    }

    #[DataProvider('reports')]
    public function test_options_do_not_reveal_other_users_records_and_empty_status_has_no_options(string $route): void
    {
        $reporter = User::factory()->create(['role' => 'reporter']);
        $this->records['A']['report']->update(['user_id' => $reporter->id]);
        $this->actingAs($reporter);
        $this->get(route($route.'.index', ['reported' => '0']))->assertOk()
            ->assertViewHas('places', fn ($places) => $places->all() === ['Lugar A'])
            ->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 1);
        $response = $this->get(route($route.'.index', ['reported' => '1']))->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['beneficiaries'] === 0);
        foreach (['states', 'municipalities', 'parishes', 'places', 'installationTypes', 'sectors', 'indicators', 'sexOptions'] as $key) {
            $response->assertViewHas($key, fn ($items) => $items->isEmpty());
        }
        $this->getJson(route($route.'.locations', ['reported' => '1']))->assertOk()
            ->assertExactJson(['states' => [], 'municipalities' => [], 'parishes' => []]);
    }
}
