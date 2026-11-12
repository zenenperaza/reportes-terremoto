<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BuzonList extends Model
{
    protected $fillable = ['proyecto_id', 'created_by'];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(BuzonEntry::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->when(! $user->isAdministrator(), fn (Builder $q) => $q
            ->whereIn('proyecto_id', $user->projects()->select('proyectos.id'))
            ->whereIn('created_by', $user->visibleGroupUserIds()));
    }

    public function scopeAppendableBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('proyecto', fn (Builder $q) => $q->where('estatus', true))
            ->when(! $user->isAdministrator(), fn (Builder $q) => $q
                ->whereIn('proyecto_id', $user->projects()->select('proyectos.id'))
                ->whereIn('created_by', $user->editableGroupUserIds()))
            // Create-only users may continue their own list, never inspect team history.
            ->when(! $user->can('ver buzon'), fn (Builder $q) => $q->where('created_by', $user->id));
    }
}
