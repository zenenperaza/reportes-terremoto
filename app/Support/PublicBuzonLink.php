<?php

namespace App\Support;

use App\Models\{Proyecto, User};
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PublicBuzonLink
{
    public static function code(Proyecto $project): string
    {
        // Codes containing slashes remain readable without becoming URL path separators.
        return Str::slug(str_replace(['/', '\\'], '-', $project->codigo)) ?: 'proyecto';
    }

    public static function available(Proyecto $project, User $recipient): bool
    {
        return $project->estatus && $recipient->is_active && ! $recipient->trashed()
            && $recipient->can('crear buzon')
            && ($recipient->isAdministrator() || $recipient->projects()->whereKey($project->id)->exists());
    }

    public static function url(Proyecto $project, User $recipient): string
    {
        return URL::signedRoute('public-buzon.show', [
            'proyecto' => $project->id, 'codigo' => self::code($project), 'recipient' => $recipient->id,
        ]);
    }
}
