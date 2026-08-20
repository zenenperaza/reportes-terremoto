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

    public function sectores(): BelongsToMany
    {
        return $this->belongsToMany(Sector::class, 'sector_proyecto', 'proyecto_id', 'sector_id')
            ->withTimestamps();
    }

    public function asignacionesSectores(): HasMany
    {
        return $this->hasMany(SectorProyecto::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'proyecto_user')->withTimestamps();
    }

    public function estados(): BelongsToMany
    {
        return $this->belongsToMany(State::class, 'estado_proyecto', 'proyecto_id', 'estado_id')->withTimestamps();
    }

    public function municipios(): BelongsToMany
    {
        return $this->belongsToMany(Municipality::class, 'municipio_proyecto', 'proyecto_id', 'municipio_id')->withTimestamps();
    }

    public function coversLocation(int $stateId, int $municipalityId): bool
    {
        $this->loadMissing(['estados:id', 'municipios:id']);

        return $this->estados->contains('id', $stateId)
            && ($this->municipios->isEmpty() || $this->municipios->contains('id', $municipalityId));
    }
}
