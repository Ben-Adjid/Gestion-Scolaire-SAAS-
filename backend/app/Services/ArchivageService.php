<?php

namespace App\Services;

use App\Models\ArchiveAnnee;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Construit et scelle l'archive d'une année : élèves, notes, absences, frais et paiements de l'établissement.
 * L'empreinte SHA-256 du contenu est conservée avec l'archive ; toute modification ultérieure est détectée.
 */
class ArchivageService
{
    public function sceller(int $etablissementId, string $anneeScolaire, User $auteur): ArchiveAnnee
    {
        $donnees = json_encode($this->instantane($etablissementId, $anneeScolaire), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $archive = ArchiveAnnee::create([
            'etablissement_id' => $etablissementId,
            'annee_scolaire' => $anneeScolaire,
            'donnees' => $donnees,
            'empreinte' => hash('sha256', $donnees),
            'archive_par' => $auteur->id,
        ]);

        AuditLogger::log($auteur, 'archivage_annee', 'archives_annee', $archive->id, "Archive scellée de l'année {$anneeScolaire}.");

        return $archive;
    }

    public function estIntacte(ArchiveAnnee $archive): bool
    {
        return hash_equals($archive->empreinte, hash('sha256', $archive->donnees));
    }

    private function instantane(int $etablissementId, string $anneeScolaire): array
    {
        return [
            'annee_scolaire' => $anneeScolaire,
            'genere_le' => now()->toIso8601String(),
            'eleves' => DB::table('etudiants')
                ->leftJoin('classes', 'classes.id', '=', 'etudiants.classe_id')
                ->where('etudiants.etablissement_id', $etablissementId)
                ->orderBy('etudiants.nom')->orderBy('etudiants.prenom')
                ->get(['etudiants.matricule', 'etudiants.nom', 'etudiants.prenom', 'classes.nom as classe', 'etudiants.date_naissance', 'etudiants.genre'])
                ->all(),
            'notes' => DB::table('notes')
                ->join('etudiants', 'etudiants.id', '=', 'notes.etudiant_id')
                ->join('matieres', 'matieres.id', '=', 'notes.matiere_id')
                ->where('etudiants.etablissement_id', $etablissementId)
                ->get(['etudiants.matricule', 'matieres.nom as matiere', 'notes.trimestre', 'notes.type', 'notes.valeur'])
                ->all(),
            'absences' => DB::table('absences')
                ->join('etudiants', 'etudiants.id', '=', 'absences.etudiant_id')
                ->where('etudiants.etablissement_id', $etablissementId)
                ->get(['etudiants.matricule', 'absences.date', 'absences.type', 'absences.justifie'])
                ->all(),
            'frais' => DB::table('frais_scolaires')
                ->join('etudiants', 'etudiants.id', '=', 'frais_scolaires.etudiant_id')
                ->where('frais_scolaires.etablissement_id', $etablissementId)
                ->get(['etudiants.matricule', 'frais_scolaires.libelle', 'frais_scolaires.montant', 'frais_scolaires.annee_scolaire'])
                ->all(),
            'paiements' => DB::table('paiements')
                ->where('etablissement_id', $etablissementId)
                ->get(['frais_scolaire_id', 'montant', 'date_paiement', 'mode_paiement', 'statut'])
                ->all(),
        ];
    }
}
