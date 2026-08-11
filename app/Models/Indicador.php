<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indicador extends Model
{
    public const ESPACIOS_COORDINACION = ['NNA', 'VBG'];
    public const UNIDADES_CONTEO = [
        'Productos / Informes / Análisis',
        'Personas',
        'Comités o mecanismos comunitarios',
        'Actividades de incidencia',
    ];
    protected $table = 'indicadores';

    protected $fillable = [
        'codigo', 'descripcion', 'unidad_conteo', 'espacio_coordinacion', 'edad_desde', 'edad_hasta',
    ];

    protected function casts(): array
    {
        return ['edad_desde' => 'integer', 'edad_hasta' => 'integer'];
    }

    public function proyectos(): BelongsToMany
    {
        return $this->belongsToMany(Proyecto::class, 'indicador_proyecto')
            ->withPivot(['id', 'estatus', 'meta_cuantitativa', 'meta_cualitativa'])
            ->withTimestamps();
    }

    public function asignacionesProyectos(): HasMany
    {
        return $this->hasMany(IndicadorProyecto::class);
    }
}
