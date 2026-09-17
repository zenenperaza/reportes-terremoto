<?php

namespace App\Policies;

use App\Models\FamilyRecord;
use App\Models\User;

class FamilyRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver casos');
    }

    public function create(User $user): bool
    {
        return $user->can('ver casos') && $user->can('crear casos');
    }

    public function view(User $user, FamilyRecord $family): bool
    {
        return $user->can('ver casos') && (! $family->restricted || $user->can('gestionar casos vbg'))
            && ($user->can('supervisar casos') || $family->assigned_to === $user->id);
    }

    public function update(User $user, FamilyRecord $family): bool
    {
        return $this->view($user, $family) && $user->can('editar casos');
    }
}
