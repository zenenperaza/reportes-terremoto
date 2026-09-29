<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingPeriod extends Model
{
    protected $fillable = ['period', 'is_closed', 'closed_at', 'updated_by'];

    protected function casts(): array
    {
        return ['is_closed' => 'boolean', 'closed_at' => 'datetime'];
    }
}
