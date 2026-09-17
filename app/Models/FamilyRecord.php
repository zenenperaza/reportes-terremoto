<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyRecord extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['registered_on' => 'date', 'restricted' => 'boolean', 'details' => 'encrypted:array', 'members' => 'encrypted:array', 'assigned_to' => 'integer', 'version' => 'integer'];
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseRecord::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->can('ver casos')) {
            return $query->whereRaw('1=0');
        }
        if (! $user->can('supervisar casos')) {
            $query->where('assigned_to', $user->id);
        }
        if (! $user->can('gestionar casos vbg')) {
            $query->where('restricted', false);
        }

        return $query;
    }
}
