<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    use HasFactory;

    protected $fillable = ['codigo', 'descripcion', 'name', 'slug', 'sort_order'];

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'sector_proyecto', 'sector_id', 'proyecto_id')
            ->withTimestamps();
    }

    public function asignacionesProyectos(): HasMany
    {
        return $this->hasMany(SectorProyecto::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }
}
