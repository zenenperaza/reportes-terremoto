<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicadorProyecto extends Model
{
    protected $table = 'indicador_proyecto';

    protected $fillable = [
        'proyecto_id', 'indicador_id', 'estatus', 'meta_cuantitativa', 'meta_cualitativa',
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
}
