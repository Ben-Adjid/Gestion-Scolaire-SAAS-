<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\ConseilClasse;
use App\Models\Etudiant;

/**
 * Vue d'ensemble d'un élève, partagée par l'espace parent et l'espace élève :
 * présences, convocations, notes validées, bulletins publiés, examens à venir, frais.
 */
class ResumeEleve
{
    public function __construct(private BulletinService $bulletins) {}

    public function pour(Etudiant $etudiant): array
    {
        $bulletins = $this->bulletins;
        $etudiant->load('classe:id,nom,niveau');

        $absences = Absence::where('etudiant_id', $etudiant->id)->orderByDesc('date')->get();

        $bulletinsPublies = [];
        foreach ([1, 2, 3] as $trimestre) {
            $pvPublie = ConseilClasse::where('classe_id', $etudiant->classe_id)->where('trimestre', $trimestre)->exists();
            if ($pvPublie) {
                $bulletinsPublies[] = $bulletins->generateBulletin($etudiant, $trimestre);
            }
        }

        $frais = \App\Models\FraisScolaire::where('etudiant_id', $etudiant->id)->with('paiements.agent:id,name')->get();

        return [
            'eleve' => $etudiant->only(['id', 'nom', 'prenom', 'matricule']),
            'classe' => $etudiant->classe?->only(['nom', 'niveau']),
            'presences' => [
                'absences' => $absences->where('type', 'absence')->count(),
                'retards' => $absences->where('type', 'retard')->count(),
                'liste' => $absences->map(fn ($a) => [
                    'id' => $a->id, 'date' => $a->date?->toDateString(), 'type' => $a->type,
                    'justifie' => (bool) $a->justifie, 'motif' => $a->motif,
                ])->values(),
            ],
            'convocations' => \App\Models\Convocation::where('etudiant_id', $etudiant->id)->orderByDesc('date')
                ->get(['date', 'motif']),
            'notes' => \App\Models\Note::where('etudiant_id', $etudiant->id)->where('statut', 'valide')
                ->with('matiere:id,nom')->orderBy('trimestre')->get()
                ->map(fn ($n) => ['matiere' => $n->matiere?->nom, 'trimestre' => $n->trimestre, 'type' => $n->type, 'valeur' => $n->valeur]),
            'bulletins' => $bulletinsPublies,
            'examens' => \App\Models\ExamenPlanning::where('classe_id', $etudiant->classe_id)
                ->whereDate('date', '>=', today())
                ->with('matiere:id,nom')
                ->orderBy('date')->orderBy('heure_debut')
                ->get()
                ->map(fn ($e) => [
                    'date' => $e->date?->toDateString(),
                    'heure' => substr((string) $e->heure_debut, 0, 5),
                    'duree_minutes' => $e->duree_minutes,
                    'salle' => $e->salle,
                    'matiere' => $e->matiere?->nom,
                ]),
            'frais' => $frais->map(fn ($f) => [
                'id' => $f->id,
                'libelle' => $f->libelle, 'annee_scolaire' => $f->annee_scolaire,
                'montant' => $f->montant_total, 'paye' => $f->montant_paye, 'reste' => $f->reste,
                // Paiements encaissés : chacun peut donner lieu à un reçu.
                'paiements' => $f->paiements->where('statut', 'completed')->sortBy('date_paiement')->values()->map(fn ($p) => [
                    'id' => $p->id,
                    'date_paiement' => $p->date_paiement?->toDateString(),
                    'montant' => (float) $p->montant,
                    'mode_paiement' => $p->mode_paiement,
                    'reference' => $p->reference,
                    'enregistre_le' => $p->created_at?->toIso8601String(),
                    'agent' => $p->agent?->name,
                ])->all(),
            ]),
        ];
    }
}
