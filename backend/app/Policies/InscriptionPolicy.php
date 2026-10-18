<?php

namespace App\Policies;

use App\Models\Inscription;
use App\Models\User;
use App\Services\TeacherScope;

class InscriptionPolicy
{
    public function view(User $user, Inscription $inscription): bool
    {
        return TeacherScope::canAccessClasse($user, (int) $inscription->classe_id);
    }
}
