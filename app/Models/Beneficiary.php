<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Beneficiary extends Model
{
    use HasFactory;

    protected $fillable = [
        'report_id', 'full_name', 'age', 'sex', 'national_id', 'phone', 'disability',
        'ethnicity', 'pregnant_lactating', 'is_recurrent',
    ];

    protected function casts(): array
    {
        return [
            'is_recurrent' => 'boolean',
            'reported' => 'boolean',
            'reported_at' => 'date',
        ];
    }

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
