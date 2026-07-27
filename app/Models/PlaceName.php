<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaceName extends Model
{
    protected $fillable = [
        'name', 'state_id', 'municipality_id', 'parish_id', 'installation_type',
        'latitude', 'longitude', 'altitude', 'gps_accuracy', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'altitude' => 'decimal:2',
            'gps_accuracy' => 'decimal:2',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function municipality()
    {
        return $this->belongsTo(Municipality::class);
    }

    public function parish()
    {
        return $this->belongsTo(Parish::class);
    }
}
