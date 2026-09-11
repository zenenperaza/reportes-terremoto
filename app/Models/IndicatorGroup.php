<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IndicatorGroup extends Model
{
    protected $fillable = ['name', 'description', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(Indicador::class, 'indicator_group_id');
    }
}
