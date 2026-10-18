<?php

namespace App\Policies;

use App\Models\Classe;
use App\Models\User;
use App\Services\TeacherScope;

class ClassePolicy
{
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'censeur']);
    }

    public function update(User $user, Classe $classe): bool
    {
        return $user->hasAnyRole(['admin', 'censeur']);
    }

    public function delete(User $user, Classe $classe): bool
    {
        return $user->hasAnyRole(['admin', 'censeur']);
    }

    public function view(User $user, Classe $classe): bool
    {
        return TeacherScope::canAccessClasse($user, (int) $classe->id);
    }
}
