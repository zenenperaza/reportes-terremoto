<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class UserPresence extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'session_hash';

    public $incrementing = false;

    protected $keyType = 'string';

    public const ACTIVE_SECONDS = 120;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function heartbeat(Request $request): void
    {
        if (! Schema::hasTable('user_presences')) {
            return;
        }
        static::upsert([[
            'session_hash' => hash('sha256', $request->session()->getId()),
            'user_id' => $request->user()->id, 'last_seen_at' => now(),
        ]], ['session_hash'], ['user_id', 'last_seen_at']);
        static::where('last_seen_at', '<', now()->subDay())->delete();
    }

    public static function connectedCount(): ?int
    {
        if (! Schema::hasTable('user_presences')) {
            return null;
        }

        return static::where('last_seen_at', '>=', now()->subSeconds(self::ACTIVE_SECONDS))
            ->whereHas('user', fn ($users) => $users->where('is_active', true))->distinct()->count('user_id');
    }

    public static function forgetSession(Request $request): void
    {
        if (Schema::hasTable('user_presences')) {
            static::where('session_hash', hash('sha256', $request->session()->getId()))->delete();
        }
    }
}
