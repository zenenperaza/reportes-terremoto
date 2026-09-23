<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class UserGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'is_active', 'allow_member_editing'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'allow_member_editing' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_group_user')->withTimestamps();
    }
}
