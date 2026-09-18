<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;

class ReportRegistrant
{
    public static function fields(User $user, ?Report $report = null): array
    {
        // Existing records retain their original snapshot, even when edited by an administrator.
        if ($report) {
            return $report->only(['reporter_first_name', 'reporter_last_name', 'reporter_email']);
        }

        $parts = preg_split('/\s+/u', trim($user->name), 2);

        return [
            'reporter_first_name' => $parts[0],
            'reporter_last_name' => $parts[1] ?? '',
            'reporter_email' => $user->email,
        ];
    }
}
