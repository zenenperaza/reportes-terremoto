<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BuzonEntry extends Model
{
    public const INDICATOR_CODE = 'GCLCO/SCA13E/CIN3';
    public const INDICATOR_NAME = 'Uso de buzón (AAP.03)';
    public const OPINIONS = ['very_good' => 'Muy buena', 'neutral' => 'Ni buena ni mala', 'bad' => 'Mala'];
    public const FEEDBACK_TYPES = ['suggestion' => 'Sugerencia', 'comment' => 'Comentario', 'complaint' => 'Queja'];
    public const GENDERS = ['female' => 'Femenino', 'male' => 'Masculino', 'non_binary_other' => 'No binario / Otro'];
    public const DISABILITIES = ['visual' => 'Visual', 'hearing' => 'Auditiva', 'motor' => 'Motriz', 'none' => 'Ninguna'];

    protected $fillable = ['proyecto_id', 'created_by', 'opinion_date', 'place_name', 'full_name', 'phone', 'age',
        'gender', 'disabilities', 'afro_descendant', 'opinion', 'feedback_type', 'comment', 'improvement', 'version'];

    protected function casts(): array
    {
        return ['opinion_date' => 'date', 'age' => 'integer', 'disabilities' => 'array', 'afro_descendant' => 'boolean', 'version' => 'integer'];
    }

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        // The route always requires Buzon; administrator status is not a permission bypass.
        return $query->when(! $user->isAdministrator(), fn (Builder $query) => $query
            ->whereIn('proyecto_id', $user->projects()->select('proyectos.id')));
    }
}
