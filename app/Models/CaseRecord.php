<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseRecord extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['registered_on' => 'date', 'birth_date' => 'date', 'consent_date' => 'date', 'share_services' => 'boolean', 'share_reports' => 'boolean', 'age_at_registration' => 'integer', 'assigned_to' => 'integer', 'version' => 'integer', 'form_data' => 'encrypted:array'];
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(FamilyRecord::class, 'family_record_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(CaseAttachment::class);
    }

    public function getWorkflowStageAttribute(): int
    {
        $data = $this->form_data ?? [];
        if ($this->status === 'closed') {
            return 6;
        }
        if (collect(data_get($data, 'services.rows', []))->contains(fn ($row) => ($row['implemented'] ?? '') === 'yes' && filled($row['implemented_on'] ?? null))) {
            return 5;
        }
        if (filled(data_get($data, 'services.rows'))) {
            return 4;
        }
        if (filled(data_get($data, 'plan.rows'))) {
            return 3;
        }
        if (filled(data_get($data, 'care.started_on')) || filled(data_get($data, 'care.rows'))) {
            return 2;
        }
        if (filled(data_get($data, 'plan.started_on'))) {
            return 1;
        }

        return 0;
    }

    public function getRouteKeyName(): string
    {
        return 'reference';
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to')->withTrashed();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function state(): BelongsTo
    {
        return $this->belongsTo(State::class);
    }

    public function municipality(): BelongsTo
    {
        return $this->belongsTo(Municipality::class);
    }

    public function parish(): BelongsTo
    {
        return $this->belongsTo(Parish::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CaseEvent::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if (! $user->can('ver casos')) {
            return $query->whereRaw('1 = 0');
        }
        if (! $user->can('supervisar casos')) {
            $query->where('assigned_to', $user->id);
        }
        if (! $user->can('gestionar casos vbg')) {
            $query->where('case_type', '!=', 'vbg');
        }

        return $query;
    }

    public function getAgeLabelAttribute(): string
    {
        if ($this->birth_date) {
            return ((int) $this->birth_date->diffInYears(today())).' años';
        }

        return $this->age_at_registration !== null ? $this->age_at_registration.' años al registrar (estimada)' : 'Sin determinar';
    }
}
