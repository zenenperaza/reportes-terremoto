<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'action',
        'method',
        'route_name',
        'path',
        'status_code',
        'duration_ms',
        'ip_address',
        'user_agent',
        'request_payload',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function deletionDetails(): ?array
    {
        if ($this->method !== 'DELETE' || $this->status_code < 200 || $this->status_code >= 400
            || ! in_array($this->route_name, ['reports.destroy', 'beneficiaries.destroy'], true)) {
            return null;
        }

        $details = $this->request_payload['deletion'] ?? null;
        return is_array($details) ? $details : null;
    }
}
