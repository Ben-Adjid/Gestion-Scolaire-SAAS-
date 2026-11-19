<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** Crée le premier compte Super Admin de la plateforme, en saisie interactive, sans données de test. */
class CreerSuperAdmin extends Command
{
    protected $signature = 'make:superadmin';

    protected $description = 'Crée le compte Super Admin de la plateforme (saisie interactive, mot de passe masqué)';

    private const MOT_DE_PASSE_MINIMUM = 12;

    public function handle(): int
    {
        $nom = trim((string) $this->ask('Nom complet'));
        if ($nom === '') {
            $this->error('Le nom est obligatoire.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->ask('Adresse e-mail')));
        $validation = Validator::make(['email' => $email], [
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ], ['email.unique' => 'Un compte existe déjà avec cette adresse e-mail.']);
        if ($validation->fails()) {
            $this->error($validation->errors()->first('email'));

            return self::FAILURE;
        }

        $motDePasse = (string) $this->secret('Mot de passe ('.self::MOT_DE_PASSE_MINIMUM.' caractères minimum)');
        if (strlen($motDePasse) < self::MOT_DE_PASSE_MINIMUM) {
            $this->error('Le mot de passe doit contenir au moins '.self::MOT_DE_PASSE_MINIMUM.' caractères.');

            return self::FAILURE;
        }

        if ($motDePasse !== (string) $this->secret('Confirmer le mot de passe')) {
            $this->error('Les deux mots de passe ne correspondent pas.');

            return self::FAILURE;
        }

        $telephone = trim((string) $this->ask('Téléphone (ex. +221770000000)'));

        User::create([
            'name' => $nom,
            'email' => $email,
            'password' => Hash::make($motDePasse),
            'role' => 'super_admin',
            'telephone' => $telephone !== '' ? $telephone : null,
            'etablissement_id' => null,
            'email_verified_at' => now(),
        ]);

        $this->info("Compte Super Admin créé pour {$email}. Connectez-vous puis changez votre mot de passe si nécessaire.");

        return self::SUCCESS;
    }
}
