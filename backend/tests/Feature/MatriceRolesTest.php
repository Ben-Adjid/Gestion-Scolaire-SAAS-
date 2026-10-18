<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MatriceRolesTest extends TestCase
{
    use RefreshDatabase;

    private const TOUS = ['admin', 'proviseur', 'censeur', 'cpe', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable', 'responsable_it'];

    /**
     * Endpoints de lecture et rôles autorisés (le super_admin passe partout).
     */
    private function matrice(): array
    {
        $eleve = Etudiant::factory()->create(['classe_id' => Classe::factory()->create()->id])->id;

        return [
            '/api/dashboard' => [self::TOUS, 'tous'],
            '/api/etudiants' => [['admin', 'proviseur', 'censeur', 'cpe', 'enseignant', 'secretaire', 'gestionnaire', 'agent_comptable'], 'élèves'],
            '/api/classes' => [self::TOUS, 'tous'],
            '/api/enseignants' => [self::TOUS, 'tous'],
            '/api/matieres' => [self::TOUS, 'tous'],
            '/api/messages' => [self::TOUS, 'tous'],
            '/api/emploi-du-temps' => [self::TOUS, 'tous'],
            '/api/inscriptions' => [['admin', 'proviseur', 'secretaire'], 'inscriptions'],
            '/api/frais-scolaires' => [['admin', 'proviseur', 'gestionnaire', 'agent_comptable'], 'frais'],
            '/api/frais-scolaires/stats' => [['admin', 'proviseur', 'gestionnaire', 'agent_comptable'], 'frais'],
            '/api/audits' => [['admin', 'responsable_it'], 'audits'],
            '/api/users' => [['admin'], 'utilisateurs'],
            '/api/palmares' => [['admin', 'proviseur', 'censeur', 'cpe'], 'palmares'],
            '/api/eleves-difficulte' => [['admin', 'proviseur', 'censeur', 'cpe'], 'suivi'],
            '/api/examens' => [['admin', 'proviseur', 'censeur', 'enseignant'], 'examens'],
            '/api/dossiers-examen' => [['admin', 'proviseur', 'censeur', 'cpe', 'secretaire'], 'dossiers'],
            '/api/absences' => [['admin', 'proviseur', 'censeur', 'cpe', 'enseignant', 'secretaire'], 'absences'],
            '/api/absences/stats' => [['admin', 'proviseur', 'censeur', 'cpe', 'enseignant', 'secretaire'], 'absences'],
            '/api/appels' => [['admin', 'censeur', 'cpe', 'enseignant'], 'appels'],
            '/api/pointages' => [['admin', 'proviseur', 'censeur'], 'pointages'],
            '/api/pointages/stats' => [['admin', 'proviseur', 'censeur'], 'pointages'],
            '/api/convocations' => [['admin', 'proviseur', 'censeur', 'cpe'], 'convocations'],
            '/api/notes' => [['admin', 'proviseur', 'censeur', 'enseignant'], 'notes'],
            '/api/absences-enseignants' => [['admin', 'proviseur', 'censeur'], 'absences enseignants'],
            "/api/etudiants/{$eleve}/bulletin" => [['admin', 'proviseur', 'censeur', 'cpe'], 'bulletins'],
        ];
    }

    public function test_lectures_protegees_par_role(): void
    {
        $erreurs = [];

        foreach ($this->matrice() as $url => [$autorises, $libelle]) {
            foreach (self::TOUS as $role) {
                Sanctum::actingAs(User::factory()->create(['role' => $role]));
                $status = $this->getJson($url)->status();

                $attendu = in_array($role, $autorises, true);
                $ok = $attendu ? $status !== 403 && $status < 500 : $status === 403;

                if (! $ok) {
                    $erreurs[] = "{$role} sur {$url} ({$libelle}) : {$status}, attendu ".($attendu ? 'accès' : '403');
                }
            }
        }

        $this->assertEmpty($erreurs, implode("\n", $erreurs));
    }
}
