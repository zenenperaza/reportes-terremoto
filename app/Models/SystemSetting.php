<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class SystemSetting extends Model
{
    public const MAINTENANCE_MODE = 'maintenance_mode';

    public const MAINTENANCE_SCHEDULE = 'maintenance_schedule';

    public const AUTOMATIC_BACKUP_LAST_AT = 'automatic_backup_last_at';

    public const CURRENT_PERIOD = 'current_period';

    protected $fillable = ['key', 'value', 'updated_by'];

    /** @return array{month: int, year: int} */
    public static function currentPeriod(): array
    {
        $value = static::query()->where('key', self::CURRENT_PERIOD)->value('value');
        if (is_string($value) && preg_match('/^(20[2-9][0-9]|2100)-(0[1-9]|1[0-2])$/', $value, $parts) && (int) $parts[1] >= 2021) {
            return ['month' => (int) $parts[2], 'year' => (int) $parts[1]];
        }

        $today = today();

        return ['month' => $today->month, 'year' => $today->year];
    }

    public static function maintenanceEnabled(): bool
    {
        return static::maintenanceState()['enabled'];
    }

    public static function maintenanceState(): array
    {
        try {
            $values = static::query()->whereIn('key', [self::MAINTENANCE_MODE, self::MAINTENANCE_SCHEDULE])->pluck('value', 'key');
            $schedule = json_decode($values[self::MAINTENANCE_SCHEDULE] ?? '', true);
            $startsAt = null;
            try {
                if (is_array($schedule) && is_string($schedule['starts_at'] ?? null)) {
                    $startsAt = Carbon::parse($schedule['starts_at']);
                }
            } catch (\Throwable) {
                // A malformed schedule must never override an immediate lock.
            }
            $enabled = ($values[self::MAINTENANCE_MODE] ?? '0') === '1' || ($startsAt && $startsAt->lte(now()));

            return [
                'enabled' => (bool) $enabled,
                'scheduled' => ! $enabled && $startsAt !== null,
                'starts_at' => $startsAt?->utc()->toIso8601String(),
                'message' => is_array($schedule) && is_string($schedule['message'] ?? null) ? $schedule['message'] : '',
            ];
        } catch (\Throwable) {
            return ['enabled' => false, 'scheduled' => false, 'starts_at' => null, 'message' => ''];
        }
    }
}
