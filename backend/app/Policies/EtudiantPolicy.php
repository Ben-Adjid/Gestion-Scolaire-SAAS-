<?php

namespace App\Policies;

use App\Models\Etudiant;
use App\Models\User;
use App\Services\TeacherScope;

class EtudiantPolicy
{
    public function view(User $user, Etudiant $etudiant): bool
    {
        return TeacherScope::canAccessClasse($user, (int) $etudiant->classe_id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'censeur', 'proviseur']);
    }

    public function update(User $user, Etudiant $etudiant): bool
    {
        return $user->hasAnyRole(['admin', 'censeur', 'proviseur']);
    }
}
