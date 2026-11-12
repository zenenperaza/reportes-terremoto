<?php

namespace App\Providers;

use App\Support\PeriodClosingNotice;
use Illuminate\Auth\Events\Login;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        Event::listen(Login::class, static function (Login $event): void {
            $request = request();
            if (! $request->hasSession() || ! $event->user->is_active) return;
            $request->session()->forget(PeriodClosingNotice::SESSION_KEY);
            if ($notice = PeriodClosingNotice::current()) {
                $request->session()->put(PeriodClosingNotice::SESSION_KEY, $notice);
            }
        });
    }
}
