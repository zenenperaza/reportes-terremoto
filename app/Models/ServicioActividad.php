<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicioActividad extends Model
{
    protected $table = 'servicio_actividad';
    protected $fillable = ['actividad_indicador_id', 'servicio_id', 'estatus', 'cantidad_disponible'];

    protected function casts(): array
    {
        return ['estatus' => 'boolean', 'cantidad_disponible' => 'integer'];
    }

    public function actividadIndicador(): BelongsTo
    {
        return $this->belongsTo(ActividadIndicador::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }
}
