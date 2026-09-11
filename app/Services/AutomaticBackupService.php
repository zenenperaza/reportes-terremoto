<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class AutomaticBackupService
{
    public const RETENTION_DAYS = 15;

    private const FAILURE_COOLDOWN_KEY = 'automatic-backup-failure-cooldown';

    private const LOCK_KEY = 'automatic-database-backup';

    public function __construct(private readonly DatabaseBackupService $backupService) {}

    public function runIfDue(): ?string
    {
        if ($this->wasCompletedToday() || Cache::has(self::FAILURE_COOLDOWN_KEY)) {
            return null;
        }

        $result = Cache::lock(self::LOCK_KEY, 900)->get(function (): ?string {
            if ($this->wasCompletedToday()) {
                return null;
            }

            try {
                $filename = $this->backupService->create();
                $this->backupService->pruneOlderThanDays(self::RETENTION_DAYS);
                SystemSetting::query()->updateOrCreate(
                    ['key' => SystemSetting::AUTOMATIC_BACKUP_LAST_AT],
                    ['value' => now()->toIso8601String(), 'updated_by' => null],
                );
                Cache::forget(self::FAILURE_COOLDOWN_KEY);

                return $filename;
            } catch (Throwable $exception) {
                Cache::put(self::FAILURE_COOLDOWN_KEY, true, now()->addHour());

                throw $exception;
            }
        });

        return is_string($result) ? $result : null;
    }

    public function lastCompletedAt(): ?Carbon
    {
        $value = SystemSetting::query()
            ->where('key', SystemSetting::AUTOMATIC_BACKUP_LAST_AT)
            ->value('value');

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function wasCompletedToday(): bool
    {
        return $this->lastCompletedAt()?->isToday() === true;
    }
}
