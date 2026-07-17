<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;

    protected $fillable = ['sector_id', 'code', 'title', 'sort_order', 'active'];

    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }
}
