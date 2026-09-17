<?php

namespace App\Policies;

use App\Models\CaseRecord;
use App\Models\User;

class CaseRecordPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('ver casos');
    }

    public function create(User $user): bool
    {
        return $user->can('ver casos') && $user->can('crear casos');
    }

    public function view(User $user, CaseRecord $caseRecord): bool
    {
        return $user->can('ver casos')
            && ($caseRecord->case_type !== 'vbg' || $user->can('gestionar casos vbg'))
            && ($user->can('supervisar casos') || $caseRecord->assigned_to === $user->id);
    }

    public function update(User $user, CaseRecord $caseRecord): bool
    {
        return $this->view($user, $caseRecord) && $user->can('editar casos');
    }

    public function history(User $user, CaseRecord $caseRecord): bool
    {
        return $this->view($user, $caseRecord) && $user->can('ver historial de casos');
    }
}
