<?php

namespace App\Policies;

use App\Models\Note;
use App\Models\User;
use App\Services\TeacherScope;

class NotePolicy
{
    public function view(User $user, Note $note): bool
    {
        return TeacherScope::canAccessMatiere($user, (int) $note->matiere_id);
    }

    public function update(User $user, Note $note): bool
    {
        if (! TeacherScope::canAccessMatiere($user, (int) $note->matiere_id)) {
            return false;
        }

        return $note->statut !== 'valide' || $user->hasAnyRole(['proviseur']);
    }

    public function delete(User $user, Note $note): bool
    {
        if (! TeacherScope::canAccessMatiere($user, (int) $note->matiere_id)) {
            return false;
        }

        return $note->statut !== 'valide' || $user->hasAnyRole(['proviseur']);
    }
}
