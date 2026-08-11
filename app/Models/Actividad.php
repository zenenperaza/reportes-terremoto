<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Actividad extends Model
{
    protected $table = 'actividades';
    protected $fillable = ['codigo', 'descripcion'];

    public function asignacionesIndicadores(): HasMany
    {
        return $this->hasMany(ActividadIndicador::class);
    }
}
