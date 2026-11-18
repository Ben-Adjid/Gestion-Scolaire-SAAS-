<?php

namespace App\Services;

use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Compte parent créé ou retrouvé à l'inscription d'un élève (formules avec l'espace parent).
 *
 * - Un parent déjà connu (même e-mail, ou même téléphone dans l'école) est simplement relié :
 *   les frères et sœurs partagent un seul compte, et une réinscription ne crée jamais de doublon.
 * - Un nouveau compte reçoit un mot de passe provisoire, affiché une seule fois à l'agent
 *   (à remettre au parent) et à changer à la première connexion. L'e-mail de bienvenue ne le contient pas.
 */
class CompteParentService
{
    public const LIENS = ['pere', 'mere', 'tuteur', 'parent'];

    /** Règles de validation des champs « parent » d'un formulaire d'inscription. */
    public static function regles(): array
    {
        return [
            'parent' => ['nullable', 'array'],
            'parent.nom' => ['required_with:parent', 'nullable', 'string', 'min:3', 'max:255'],
            'parent.email' => ['nullable', 'email', 'max:255'],
            'parent.telephone' => ['nullable', 'string', 'max:30'],
            'parent.lien' => ['nullable', 'in:'.implode(',', self::LIENS)],
        ];
    }

    /** L'espace parent fait-il partie de la formule de l'école de l'élève ? */
    public function disponible(Etudiant $etudiant): bool
    {
        return in_array('espace_parent', $etudiant->etablissement?->fonctionnalites() ?? [], true);
    }

    /**
     * Relie l'élève à son parent, en créant le compte au besoin.
     *
     * @return array{statut: string, parent: array, mot_de_passe_provisoire: ?string}|null
     */
    public function rattacher(Etudiant $etudiant, ?array $donnees, ?User $agent): ?array
    {
        if (! $donnees || blank($donnees['nom'] ?? null) || ! $this->disponible($etudiant)) {
            return null;
        }

        $email = filled($donnees['email'] ?? null) ? mb_strtolower(trim($donnees['email'])) : null;
        $telephone = filled($donnees['telephone'] ?? null) ? trim($donnees['telephone']) : null;
        $lien = $donnees['lien'] ?? 'parent';

        $parent = $this->retrouver($etudiant, $email, $telephone);
        $motDePasse = null;

        if ($parent) {
            $statut = $parent->enfants()->where('etudiants.id', $etudiant->id)->exists() ? 'deja_lie' : 'relie';
            // Complète le téléphone s'il manquait (utile pour WhatsApp), sans écraser une donnée existante.
            if ($telephone && blank($parent->telephone)) {
                $parent->forceFill(['telephone' => $telephone])->save();
            }
        } else {
            if (! $email) {
                throw ValidationException::withMessages([
                    'parent.email' => ['L’e-mail du parent est nécessaire pour lui créer un compte (il sert d’identifiant de connexion).'],
                ]);
            }

            $motDePasse = $this->motDePasseProvisoire();
            $parent = User::create([
                'name' => trim($donnees['nom']),
                'email' => $email,
                'telephone' => $telephone,
                'role' => 'parent',
                'password' => Hash::make($motDePasse),
                'etablissement_id' => $etudiant->etablissement_id,
            ]);
            $parent->forceFill(['doit_changer_mot_de_passe' => true])->save();
            $statut = 'cree';

            try {
                Mail::to($parent->email)->send(new \App\Mail\CompteCree(
                    $parent->name, 'Parent', $etudiant->etablissement?->nom ?? 'votre établissement',
                ));
            } catch (\Throwable $e) {
                // Le compte existe même si l'e-mail n'a pas pu partir : l'agent remet les identifiants en main propre.
            }
        }

        $parent->enfants()->syncWithoutDetaching([$etudiant->id => ['lien' => $lien]]);

        if ($statut !== 'deja_lie') {
            AuditLogger::log($agent, $statut === 'cree' ? 'creation_compte_parent' : 'lien_parent', 'Etudiant', $etudiant->id,
                ($statut === 'cree' ? 'Compte parent créé et relié : ' : 'Parent existant relié : ')
                ."{$parent->name} → {$etudiant->prenom} {$etudiant->nom}");
        }

        return [
            'statut' => $statut,
            'parent' => ['id' => $parent->id, 'nom' => $parent->name, 'email' => $parent->email, 'telephone' => $parent->telephone],
            'mot_de_passe_provisoire' => $motDePasse,
        ];
    }

    /** Parent existant : même e-mail (compte parent de la même école), sinon même téléphone dans l'école. */
    private function retrouver(Etudiant $etudiant, ?string $email, ?string $telephone): ?User
    {
        if ($email) {
            $compte = User::withoutGlobalScopes()->whereRaw('LOWER(email) = ?', [$email])->first();
            if ($compte) {
                if ($compte->role !== 'parent' || (int) $compte->etablissement_id !== (int) $etudiant->etablissement_id) {
                    throw ValidationException::withMessages([
                        'parent.email' => ['Cet e-mail est déjà utilisé par un autre compte. Indiquez une autre adresse pour le parent.'],
                    ]);
                }

                return $compte;
            }
        }

        $chiffres = $telephone ? substr(preg_replace('/\D+/', '', $telephone), -8) : '';
        if (strlen($chiffres) === 8) {
            return User::withoutGlobalScopes()
                ->where('role', 'parent')
                ->where('etablissement_id', $etudiant->etablissement_id)
                ->whereNotNull('telephone')
                ->get()
                ->first(fn (User $u) => substr(preg_replace('/\D+/', '', (string) $u->telephone), -8) === $chiffres);
        }

        return null;
    }

    /** Mot de passe lisible à dicter ou recopier : sans caractères ambigus (0/O, 1/l/I). */
    private function motDePasseProvisoire(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $mdp = '';
        for ($i = 0; $i < 10; $i++) {
            $mdp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $mdp;
    }
}
