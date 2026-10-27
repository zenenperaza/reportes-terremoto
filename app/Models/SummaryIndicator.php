<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SummaryIndicator extends Model
{
    protected $fillable = ['proyecto_id', 'code', 'name', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'proyecto_id' => 'integer'];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function assignments(): BelongsToMany
    {
        return $this->belongsToMany(IndicadorProyecto::class, 'summary_indicator_assignments', 'summary_indicator_id', 'indicador_proyecto_id')->withTimestamps();
    }
}
