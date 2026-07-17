<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evidence extends Model
{
    use HasFactory;

    protected $fillable = ['report_id', 'slot', 'original_name', 'path', 'mime_type', 'size'];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
