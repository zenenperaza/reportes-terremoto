<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Servicio extends Model
{
    protected $table = 'servicios';
    protected $fillable = ['nombre', 'descripcion'];

    public function asignacionesActividades(): HasMany
    {
        return $this->hasMany(ServicioActividad::class);
    }
}
