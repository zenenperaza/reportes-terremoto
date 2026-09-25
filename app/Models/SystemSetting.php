<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    public const MAINTENANCE_MODE = 'maintenance_mode';

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
        try {
            return static::query()->where('key', self::MAINTENANCE_MODE)->value('value') === '1';
        } catch (\Throwable) {
            return false;
        }
    }
}
