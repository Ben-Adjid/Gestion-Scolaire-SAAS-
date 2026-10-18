<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\User;
use App\Services\TeacherScope;

class AbsencePolicy
{
    public function view(User $user, Absence $absence): bool
    {
        return TeacherScope::canAccessClasse($user, (int) $absence->etudiant()->value('classe_id'));
    }
}
