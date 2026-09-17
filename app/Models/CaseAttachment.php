<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseAttachment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['original_name' => 'encrypted'];
    }

    public function caseRecord(): BelongsTo
    {
        return $this->belongsTo(CaseRecord::class);
    }
}
