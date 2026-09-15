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
}
