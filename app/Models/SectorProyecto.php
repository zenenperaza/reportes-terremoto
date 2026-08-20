<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectorProyecto extends Model
{
    protected $table = 'sector_proyecto';

    protected $fillable = ['proyecto_id', 'sector_id'];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function sector(): BelongsTo
    {
        return $this->belongsTo(Sector::class);
    }

    public function asignacionesIndicadores(): HasMany
    {
        return $this->hasMany(IndicadorProyecto::class);
    }
}
