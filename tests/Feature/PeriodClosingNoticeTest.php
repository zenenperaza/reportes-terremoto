<?php

namespace Tests\Feature;

use App\Models\{EmailTwoFactorCode, ReportingPeriod, SystemSetting, User};
use App\Notifications\EmailTwoFactorCodeNotification;
use App\Support\PeriodClosingNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PeriodClosingNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function period(string $period): void
    {
        SystemSetting::updateOrCreate(['key' => SystemSetting::CURRENT_PERIOD], ['value' => $period]);
    }

    private function at(string $date): void
    {
        $this->travelTo(Carbon::parse($date, 'America/Caracas'));
    }

    public static function boundaries(): array
    {
        return [
            'before November window' => ['2026-11', '2026-11-24 23:59:59', false],
            'five days before November end' => ['2026-11', '2026-11-25 00:00:00', true],
            'November middle of window' => ['2026-11', '2026-11-28 12:00:00', true],
            'November last second' => ['2026-11', '2026-11-30 23:59:59', true],
            'after November' => ['2026-11', '2026-12-01 00:00:00', false],
            '31-day month before window' => ['2026-12', '2026-12-25 23:59:59', false],
            '31-day month start' => ['2026-12', '2026-12-26 00:00:00', true],
            'year end' => ['2026-12', '2026-12-31 23:59:59', true],
            'new year' => ['2026-12', '2027-01-01 00:00:00', false],
            'February before window' => ['2027-02', '2027-02-22 23:59:59', false],
            'February start' => ['2027-02', '2027-02-23 00:00:00', true],
            'February end' => ['2027-02', '2027-02-28 23:59:59', true],
            'leap year before window' => ['2028-02', '2028-02-23 23:59:59', false],
            'leap year start' => ['2028-02', '2028-02-24 00:00:00', true],
            'leap year last day' => ['2028-02', '2028-02-29 23:59:59', true],
            'configured previous month' => ['2026-10', '2026-11-26 12:00:00', false],
            'configured future month' => ['2026-12', '2026-11-26 12:00:00', false],
        ];
    }

    #[DataProvider('boundaries')]
    public function test_notice_uses_the_configured_open_period_and_exact_month_end_window(string $period, string $instant, bool $expected): void
    {
        $this->period($period);
        $this->at($instant);
        $settings = SystemSetting::pluck('value', 'key')->all();
        $notice = PeriodClosingNotice::current();
        $this->assertSame($expected, $notice !== null);
        if ($expected) $this->assertSame($period, $notice['period']);
        $this->assertDatabaseCount('reporting_periods', 0);
        $this->assertSame($settings, SystemSetting::pluck('value', 'key')->all());
    }

    public static function roles(): array
    {
        return [['admin'], ['coordinator'], ['reporter']];
    }

    #[DataProvider('roles')]
    public function test_successful_login_shows_the_notice_once_then_reappears_on_the_next_login(string $role): void
    {
        $this->period('2026-11');
        $this->at('2026-11-25 09:00:00');
        $user = User::factory()->create(['role' => $role, 'requires_two_factor' => false, 'is_active' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('dashboard'))->assertSessionHas(PeriodClosingNotice::SESSION_KEY)
            ->assertSessionHas('desktop_login_event');
        $page = $this->get(route('dashboard'))->assertOk()->assertSee('id="period-closing-notice"', false)
            ->assertSee('Noviembre 2026')->assertSee('30/11/2026')->assertSee('datos serán enviados al donante')
            ->assertSee('Entendido')->assertSee('period-closing-notice.js')->assertSee('period-closing-notice.css');
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$page->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//head/link[contains(@href,"period-closing-notice.css")]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="period-closing-notice"]//button[@type="button"]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="period-closing-notice"]//form')->length);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('id="period-closing-notice"', false);
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();
        $this->get(route('dashboard'))->assertOk()->assertSee('id="period-closing-notice"', false);
        $this->assertDatabaseCount('reporting_periods', 0);
    }

    public function test_failed_or_inactive_logins_do_not_receive_a_notice(): void
    {
        $this->period('2026-11');
        $this->at('2026-11-26 09:00:00');
        $user = User::factory()->create(['requires_two_factor' => false]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'incorrect'])
            ->assertSessionHasErrors('email')->assertSessionMissing(PeriodClosingNotice::SESSION_KEY);
        $this->assertGuest();
        $user->update(['is_active' => false]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email')->assertSessionMissing(PeriodClosingNotice::SESSION_KEY);
        $this->get(route('login'))->assertOk()->assertDontSee('id="period-closing-notice"', false);
    }

    public function test_two_factor_notice_is_only_set_after_successful_code_verification(): void
    {
        Notification::fake();
        $this->period('2026-11');
        $this->at('2026-11-25 09:00:00');
        $user = User::factory()->create(['requires_two_factor' => true]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.challenge'))->assertSessionMissing(PeriodClosingNotice::SESSION_KEY);
        $code = null;
        Notification::assertSentTo($user, EmailTwoFactorCodeNotification::class, function ($notification) use (&$code) {
            $code = $notification->code;
            return true;
        });
        $invalid = $code === '111111' ? '222222' : '111111';
        $this->post(route('two-factor.verify'), ['code' => $invalid])->assertSessionHasErrors('code')
            ->assertSessionMissing(PeriodClosingNotice::SESSION_KEY);
        $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect(route('dashboard'))
            ->assertSessionHas(PeriodClosingNotice::SESSION_KEY);
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(EmailTwoFactorCode::sole()->consumed_at);
        $this->get(route('dashboard'))->assertOk()->assertSee('Aviso de cierre de período');
    }

    public function test_intended_destination_does_not_lose_the_notice(): void
    {
        $this->period('2026-11');
        $this->at('2026-11-25 09:00:00');
        $user = User::factory()->create(['requires_two_factor' => false]);
        $this->get(route('buzon.create'))->assertRedirect(route('login'));
        $user->givePermissionTo('crear buzon');
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('buzon.create'))->assertSessionHas(PeriodClosingNotice::SESSION_KEY);
        $this->get(route('buzon.create'))->assertOk()->assertSee('Aviso de cierre de período');
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Aviso de cierre de período');
    }

    public function test_closed_period_or_date_outside_window_does_not_show_an_upcoming_closure(): void
    {
        $this->period('2026-11');
        $this->at('2026-11-27 09:00:00');
        ReportingPeriod::create(['period' => '2026-11', 'is_closed' => true, 'closed_at' => now()]);
        $before = ReportingPeriod::sole()->getAttributes();
        $user = User::factory()->create(['requires_two_factor' => false]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionMissing(PeriodClosingNotice::SESSION_KEY);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Aviso de cierre de período');
        $this->assertSame($before, ReportingPeriod::sole()->getAttributes());
        $this->post(route('logout'));
        $this->at('2026-11-20 09:00:00');
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionMissing(PeriodClosingNotice::SESSION_KEY);
    }

    public function test_time_window_uses_caracas_even_when_the_app_is_in_utc(): void
    {
        config(['app.timezone' => 'UTC']);
        $this->period('2026-11');
        $this->travelTo(Carbon::parse('2026-11-25 03:59:59', 'UTC'));
        $this->assertNull(PeriodClosingNotice::current()); // November 24, 23:59:59 Caracas.
        $this->travelTo(Carbon::parse('2026-11-25 04:00:00', 'UTC'));
        $this->assertNotNull(PeriodClosingNotice::current());
        $this->travelTo(Carbon::parse('2026-12-01 03:59:59', 'UTC'));
        $this->assertNotNull(PeriodClosingNotice::current()); // Still November 30 in Caracas.
        $this->travelTo(Carbon::parse('2026-12-01 04:00:00', 'UTC'));
        $this->assertNull(PeriodClosingNotice::current());
    }

    public function test_remembered_authentication_shows_the_same_notice_once_without_new_database_notifications(): void
    {
        $this->period('2026-11');
        $this->at('2026-11-27 09:00:00');
        $user = User::factory()->create(['remember_token' => 'remember-period-test-token', 'requires_two_factor' => false]);
        $guard = auth()->guard('web');
        $cookie = $user->id.'|'.$user->getRememberToken().'|'.$guard->hashPasswordForCookie($user->getAuthPassword());
        $before = \App\Models\SystemNotification::count();
        $this->withCookie($guard->getRecallerName(), $cookie)->get(route('dashboard'))->assertOk()->assertSee('Aviso de cierre de período');
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($guard->viaRemember());
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Aviso de cierre de período');
        $this->assertSame($before, \App\Models\SystemNotification::count());
    }

    public function test_a_period_closed_after_login_does_not_display_an_outdated_upcoming_warning(): void
    {
        $this->period('2026-11');
        $this->at('2026-11-27 09:00:00');
        $user = User::factory()->create(['requires_two_factor' => false]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHas(PeriodClosingNotice::SESSION_KEY);
        ReportingPeriod::create(['period' => '2026-11', 'is_closed' => true, 'closed_at' => now()]);
        $this->get(route('dashboard'))->assertOk()->assertDontSee('Aviso de cierre de período');
        $this->assertFalse(session()->has(PeriodClosingNotice::SESSION_KEY));
    }
}
