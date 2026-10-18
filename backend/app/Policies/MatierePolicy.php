<?php

namespace App\Policies;

use App\Models\Matiere;
use App\Models\User;
use App\Services\TeacherScope;

class MatierePolicy
{
    public function view(User $user, Matiere $matiere): bool
    {
        return TeacherScope::canAccessMatiere($user, (int) $matiere->id);
    }
}
