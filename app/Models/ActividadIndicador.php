<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActividadIndicador extends Model
{
    protected $table = 'actividad_indicador';
    protected $fillable = ['indicador_proyecto_id', 'actividad_id', 'estatus', 'meta'];

    protected function casts(): array
    {
        return ['estatus' => 'boolean', 'meta' => 'integer'];
    }

    public function indicadorProyecto(): BelongsTo
    {
        return $this->belongsTo(IndicadorProyecto::class);
    }

    public function actividad(): BelongsTo
    {
        return $this->belongsTo(Actividad::class);
    }

    public function asignacionesServicios(): HasMany
    {
        return $this->hasMany(ServicioActividad::class);
    }
}
