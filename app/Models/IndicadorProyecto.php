<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndicadorProyecto extends Model
{
    protected $table = 'indicador_proyecto';

    protected $fillable = [
        'proyecto_id', 'sector_proyecto_id', 'indicador_id', 'estatus', 'meta_cuantitativa', 'meta_cualitativa',
    ];

    protected function casts(): array
    {
        return ['estatus' => 'boolean', 'meta_cuantitativa' => 'integer'];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function indicador(): BelongsTo
    {
        return $this->belongsTo(Indicador::class);
    }

    public function asignacionSector(): BelongsTo
    {
        return $this->belongsTo(SectorProyecto::class, 'sector_proyecto_id');
    }

    public function asignacionesActividades(): HasMany
    {
        return $this->hasMany(ActividadIndicador::class);
    }
}
