<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class PeriodClosingNotice
{
    public const SESSION_KEY = 'period_closing_notice';
    private const TIMEZONE = 'America/Caracas';
    private const DAYS_BEFORE_MONTH_END = 5;

    /** This is a reminder, not a scheduled closure or donor submission. */
    public static function current(): ?array
    {
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $period = ReportPeriod::current();
        $lastDay = CarbonImmutable::createFromFormat('!Y-m', $period, self::TIMEZONE)->endOfMonth()->startOfDay();
        if ($today->lt($lastDay->subDays(self::DAYS_BEFORE_MONTH_END)) || $today->gt($lastDay)
            || ReportPeriod::isClosed($period)) {
            return null;
        }

        return ['period' => $period, 'label' => ReportPeriod::label($period), 'last_day' => $lastDay->format('d/m/Y')];
    }
}
