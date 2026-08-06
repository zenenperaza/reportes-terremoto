<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proyecto extends Model
{
    protected $fillable = ['donante_id', 'estatus', 'codigo', 'descripcion', 'inicio', 'fin'];

    protected function casts(): array
    {
        return ['estatus' => 'boolean', 'inicio' => 'date', 'fin' => 'date'];
    }

    public function donante(): BelongsTo
    {
        return $this->belongsTo(Donante::class);
    }

    public function indicadores(): BelongsToMany
    {
        return $this->belongsToMany(Indicador::class, 'indicador_proyecto')
            ->withPivot(['id', 'estatus', 'meta_cuantitativa', 'meta_cualitativa'])
            ->withTimestamps();
    }

    public function asignacionesIndicadores(): HasMany
    {
        return $this->hasMany(IndicadorProyecto::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'proyecto_user')->withTimestamps();
    }
}
