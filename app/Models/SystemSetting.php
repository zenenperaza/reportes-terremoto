<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    public const MAINTENANCE_MODE = 'maintenance_mode';

    protected $fillable = ['key', 'value', 'updated_by'];

    public static function maintenanceEnabled(): bool
    {
        try {
            return static::query()->where('key', self::MAINTENANCE_MODE)->value('value') === '1';
        } catch (\Throwable) {
            return false;
        }
    }
}
