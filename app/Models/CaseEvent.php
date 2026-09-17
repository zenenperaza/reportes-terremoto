<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changed_fields' => 'array'];
    }
}
