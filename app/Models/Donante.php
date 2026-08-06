<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donante extends Model
{
    protected $fillable = ['nombre', 'estatus', 'enlaces'];

    protected function casts(): array
    {
        return ['estatus' => 'boolean'];
    }

    public function proyectos(): HasMany
    {
        return $this->hasMany(Proyecto::class);
    }
}
