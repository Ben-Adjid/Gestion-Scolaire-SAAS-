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
        if (! $this->peutAgirSur($user, $note)) {
            return false;
        }

        return $note->statut !== 'valide' || $user->hasAnyRole(['proviseur']);
    }

    /**
     * Un enseignant agit sur les matières qui lui sont affectées ; un compte doté de la permission
     * « notes.modifier » agit sur toutes les notes (les notes validées restent réservées au proviseur).
     */
    private function peutAgirSur(User $user, Note $note): bool
    {
        if ($user->hasPermission('notes.modifier')) {
            return true;
        }

        // Sans permission : seuls la direction et les enseignants affectés à la matière peuvent modifier une note.
        if (! $user->hasAnyRole(['enseignant', 'proviseur', 'censeur', 'admin'])) {
            return false;
        }

        return TeacherScope::canAccessMatiere($user, (int) $note->matiere_id);
    }

    public function delete(User $user, Note $note): bool
    {
        if (! $this->peutAgirSur($user, $note)) {
            return false;
        }

        return $note->statut !== 'valide' || $user->hasAnyRole(['proviseur']);
    }
}
