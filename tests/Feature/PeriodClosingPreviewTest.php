<?php

namespace Tests\Feature;

use App\Models\{Beneficiary, BuzonEntry, BuzonList, Report, ReportingPeriod, SystemNotification, SystemSetting, User};
use App\Support\PeriodClosingNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PeriodClosingPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-12 09:00:00', 'America/Caracas'));
        SystemSetting::updateOrCreate(['key' => SystemSetting::CURRENT_PERIOD], ['value' => '2026-11']);
    }

    public function test_admin_can_open_the_same_notice_outside_the_real_date_window_without_persisting_a_simulation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $settings = SystemSetting::pluck('value', 'key')->all();
        $instant = now()->toIso8601String();
        $counts = $this->domainCounts();
        $this->assertNull(PeriodClosingNotice::current());

        $this->actingAs($admin)->get(route('system-maintenance.index'))->assertOk()
            ->assertSee('Simular aviso de cierre')->assertDontSee('id="period-closing-notice"', false);
        $page = $this->get(route('system-maintenance.period-closing-preview'))->assertOk()
            ->assertSee('id="period-closing-notice"', false)->assertSee('Simulación — faltan 5 días')
            ->assertSee('25/11/2026')->assertSee('30/11/2026')->assertSee('Noviembre 2026')
            ->assertSee('Esta vista previa solo la ve usted.')->assertSee('Entendido')
            ->assertSee('period-closing-notice.css')->assertSee('period-closing-notice.js')
            ->assertSessionMissing(PeriodClosingNotice::SESSION_KEY);
        $this->assertTrue($page->headers->hasCacheControlDirective('no-store'));
        $this->assertSame($instant, now()->toIso8601String());
        $this->assertSame($settings, SystemSetting::pluck('value', 'key')->all());
        $this->assertSame($counts, $this->domainCounts());
        $this->assertNull(PeriodClosingNotice::current());

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$page->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//*[@id="period-closing-notice"]')->length);
        $this->assertSame(1, $xpath->query('//head/link[contains(@href,"period-closing-notice.css")]')->length);
        $this->assertSame(1, $xpath->query('//script[contains(@src,"period-closing-notice.js")]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="period-closing-notice"]//button[@type="button"]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="period-closing-notice"]//form')->length);

        $this->get(route('system-maintenance.index'))->assertOk()->assertDontSee('id="period-closing-notice"', false);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('id="period-closing-notice"', false);
        $this->actingAs(User::factory()->create(['role' => 'reporter']))->get(route('dashboard'))->assertOk()
            ->assertDontSee('id="period-closing-notice"', false)->assertDontSee('Simular aviso de cierre');
    }

    public static function months(): array
    {
        return [
            ['2026-11', '25/11/2026', '30/11/2026'],
            ['2026-12', '26/12/2026', '31/12/2026'],
            ['2027-02', '23/02/2027', '28/02/2027'],
            ['2028-02', '24/02/2028', '29/02/2028'],
        ];
    }

    #[DataProvider('months')]
    public function test_preview_uses_the_configured_period_and_five_days_before_its_last_day(string $period, string $simulated, string $last): void
    {
        SystemSetting::where('key', SystemSetting::CURRENT_PERIOD)->update(['value' => $period]);
        $preview = PeriodClosingNotice::preview();
        $this->assertSame($period, $preview['period']);
        $this->assertSame($simulated, $preview['simulated_day']);
        $this->assertSame($last, $preview['last_day']);
        $this->assertSame('2026-11-12', now('America/Caracas')->toDateString());
        $this->assertDatabaseCount('reporting_periods', 0);
    }

    public function test_preview_does_not_reopen_closed_periods_or_change_maintenance_schedules(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ReportingPeriod::create(['period' => '2026-11', 'is_closed' => true, 'closed_at' => now()]);
        $closed = ReportingPeriod::sole()->getAttributes();
        $this->actingAs($admin)->put(route('system-maintenance.update'), ['enabled' => true, 'delay_minutes' => 10])->assertRedirect();
        $settings = SystemSetting::pluck('value', 'key')->all();
        $state = SystemSetting::maintenanceState();
        $this->get(route('system-maintenance.period-closing-preview'))->assertOk()->assertSee('aunque esté cerrado');
        $this->assertSame($settings, SystemSetting::pluck('value', 'key')->all());
        $this->assertSame($state, SystemSetting::maintenanceState());
        $this->assertSame($closed, ReportingPeriod::sole()->getAttributes());
        $this->assertNull(PeriodClosingNotice::current());

        $this->put(route('system-maintenance.update'), ['enabled' => true])->assertRedirect();
        $this->get(route('system-maintenance.period-closing-preview'))->assertOk()->assertSee('SISTEMA BLOQUEADO');
        $this->assertTrue(SystemSetting::maintenanceEnabled());
    }

    public function test_preview_preserves_a_real_pending_login_notice_in_the_admin_session(): void
    {
        $this->travelTo(Carbon::parse('2026-11-25 09:00:00', 'America/Caracas'));
        $pending = PeriodClosingNotice::current();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->withSession([PeriodClosingNotice::SESSION_KEY => $pending])
            ->get(route('system-maintenance.period-closing-preview'))->assertOk()
            ->assertSessionHas(PeriodClosingNotice::SESSION_KEY, $pending);
        $this->get(route('dashboard'))->assertOk()->assertSee('id="period-closing-notice"', false)
            ->assertDontSee('Simulación — faltan 5 días')->assertSessionMissing(PeriodClosingNotice::SESSION_KEY);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('id="period-closing-notice"', false);
    }

    public function test_guests_non_admins_and_inactive_admins_cannot_open_the_preview(): void
    {
        $this->get(route('system-maintenance.period-closing-preview'))->assertRedirect(route('login'));
        foreach (['reporter', 'coordinator'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson(route('system-maintenance.period-closing-preview'))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => false]))
            ->getJson(route('system-maintenance.period-closing-preview'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_query_parameters_cannot_turn_the_normal_notice_into_a_preview_or_choose_an_arbitrary_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $options = ['preview' => true, 'periodClosingPreview' => ['label' => 'Injected'], 'date' => '2026-11-25', 'period' => '2035-12'];
        $this->actingAs($admin)->get(route('system-maintenance.index', $options))->assertOk()
            ->assertDontSee('id="period-closing-notice"', false);
        $this->get(route('system-maintenance.period-closing-preview', $options))->assertOk()
            ->assertSee('25/11/2026')->assertSee('Noviembre 2026')->assertDontSee('Injected')->assertDontSee('2035-12');
        $this->assertSame('2026-11', SystemSetting::where('key', SystemSetting::CURRENT_PERIOD)->value('value'));
        $this->assertSame('2026-11-12', now('America/Caracas')->toDateString());
    }

    private function domainCounts(): array
    {
        return array_map(fn ($model) => $model::count(), [
            Report::class, Beneficiary::class, BuzonEntry::class, BuzonList::class, ReportingPeriod::class, SystemNotification::class,
        ]);
    }
}
