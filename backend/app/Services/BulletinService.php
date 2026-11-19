<?php

namespace App\Services;

use App\Models\Etudiant;
use App\Models\Regle;
use Illuminate\Support\Collection;

class BulletinService
{
    public function thresholds(): array
    {
        $regle = Regle::first();

        return [
            'note_max' => 20,
            'seuil_admission' => (float) ($regle->seuil_admission ?? 10),
            'encouragements' => (float) ($regle->seuil_bien ?? 12),
            'honneur' => (float) ($regle->seuil_tres_bien ?? 14),
            'felicitations' => (float) ($regle->seuil_excellence ?? 16),
        ];
    }

    /** Ordre des niveaux, du plus bas au plus haut : sert à savoir si une réinscription est une montée. */
    public const ORDRE_NIVEAUX = ['6ème', '5ème', '4ème', '3ème', 'Seconde', 'Première', 'Terminale'];

    public function rangNiveau(?string $niveau): ?int
    {
        $indice = array_search(trim((string) $niveau), self::ORDRE_NIVEAUX, true);

        return $indice === false ? null : $indice;
    }

    /**
     * Passage en classe supérieure : l'élève doit être admis (moyenne annuelle au seuil d'admission).
     * Renvoie null si le passage est autorisé, sinon le motif à afficher.
     */
    public function motifRefusPassage(Etudiant $etudiant, ?string $niveauCible, ?string $niveauActuel = null): ?string
    {
        $rangActuel = $this->rangNiveau($niveauActuel ?? $etudiant->classe?->niveau);
        $rangCible = $this->rangNiveau($niveauCible);
        if ($rangActuel === null || $rangCible === null || $rangCible <= $rangActuel) {
            return null;
        }

        $seuil = $this->thresholds()['seuil_admission'];
        $moyenne = $this->moyenneAnnuelle($etudiant);
        if ($moyenne === null) {
            return 'Impossible d’inscrire cet élève en classe supérieure : aucune note annuelle enregistrée.';
        }
        if ($moyenne < $seuil) {
            return "Impossible d’inscrire cet élève en classe supérieure : sa moyenne annuelle est de {$moyenne}/20, le seuil d’admission est de {$seuil}/20.";
        }

        return null;
    }

    public function decision(?float $moyenne): array
    {
        if ($moyenne === null) {
            return ['statut' => 'inconnu', 'label' => 'Pas de notes'];
        }

        return $moyenne >= $this->thresholds()['seuil_admission']
            ? ['statut' => 'admis', 'label' => 'Admis(e)']
            : ['statut' => 'redouble', 'label' => 'Redouble'];
    }

    public function appreciationNote(float $valeur): string
    {
        return match (true) {
            $valeur >= 16 => 'Excellent',
            $valeur >= 14 => 'Très bien',
            $valeur >= 12 => 'Bien',
            $valeur >= 10 => 'Passable',
            default => 'Doit progresser',
        };
    }

    /**
     * Année à partir de laquelle on juge un élève : celle de sa dernière inscription
     * (l'année qu'il vient de terminer lors d'une réinscription), sinon celle de sa fiche.
     */
    /**
     * Année du bulletin : la dernière année inscrite qui a des notes. Un élève réinscrit mais pas encore noté
     * garde ainsi le bulletin de son année précédente, avec sa classe et sa moyenne de cette année-là.
     */
    public function anneeDeReference(Etudiant $etudiant): ?string
    {
        $annees = $etudiant->inscriptions()->orderByDesc('date_inscription')->orderByDesc('id')->pluck('annee_scolaire')->unique()->values();

        foreach ($annees as $annee) {
            if ($etudiant->notes()->where('annee_scolaire', $annee)->exists()) {
                return $annee;
            }
        }

        return $annees->first() ?: $etudiant->annee_scolaire;
    }

    /** Classe de l'élève pour une année donnée (inscription de cette année), sinon sa classe actuelle. */
    public function classeDeReference(Etudiant $etudiant, ?string $annee): ?int
    {
        $inscription = $annee
            ? $etudiant->inscriptions()->where('annee_scolaire', $annee)->orderByDesc('id')->first()
            : null;

        return $inscription?->classe_id ?? $etudiant->classe_id;
    }

    public function moyenneAnnuelle(Etudiant $etudiant, ?string $annee = null): ?float
    {
        $annee ??= $this->anneeDeReference($etudiant);

        $notes = $etudiant->notes()
            ->when($annee, fn ($q) => $q->where('annee_scolaire', $annee))
            ->with('matiere')
            ->get();

        return $notes->isEmpty() ? null : round($this->moyennePonderee($notes), 2);
    }

    public function mention(float $moyenne): array
    {
        $t = $this->thresholds();

        return match (true) {
            $moyenne >= $t['felicitations'] => ['key' => 'felicitations', 'label' => 'Félicitations'],
            $moyenne >= $t['honneur'] => ['key' => 'honneur', 'label' => "Tableau d'honneur"],
            $moyenne >= $t['encouragements'] => ['key' => 'encouragements', 'label' => 'Encouragements'],
            $moyenne >= $t['seuil_admission'] => ['key' => 'passable', 'label' => 'Passable'],
            default => ['key' => 'insuffisant', 'label' => 'Insuffisant'],
        };
    }

    /**
     * Moyenne pondérée : chaque matière compte pour la moyenne de ses notes, multipliée par son coefficient.
     *
     * @return array<int, float> etudiant_id => moyenne, trié par moyenne décroissante
     */
    public function computeClassAverages(array $etudiantIds, ?int $trimestre = 1, ?string $annee = null): array
    {
        if ($etudiantIds === []) {
            return [];
        }

        $notes = \App\Models\Note::query()
            ->whereIn('etudiant_id', $etudiantIds)
            ->when($annee, fn ($q) => $q->where('annee_scolaire', $annee))
            ->when($trimestre, fn ($q) => $q->where('trimestre', $trimestre))
            ->with('matiere:id,coefficient')
            ->get(['id', 'etudiant_id', 'matiere_id', 'valeur']);

        $averages = [];
        foreach ($notes->groupBy('etudiant_id') as $etudiantId => $notesEtudiant) {
            $averages[(int) $etudiantId] = $this->moyennePonderee($notesEtudiant);
        }

        arsort($averages);

        return $averages;
    }

    public function generateBulletin(Etudiant $etudiant, ?int $trimestre = 1): array
    {
        $annee = $this->anneeDeReference($etudiant);
        $notes = $etudiant->notes()
            ->when($annee, fn ($q) => $q->where('annee_scolaire', $annee))
            ->when($trimestre, fn ($q) => $q->where('trimestre', $trimestre))
            ->with('matiere')
            ->get();

        $classeId = $this->classeDeReference($etudiant, $annee);
        $statsClasse = $this->statsParMatiereClasse($etudiant, $trimestre, $annee, $classeId);

        $matieres = $notes->groupBy('matiere_id')->map(function (Collection $notesMatiere) use ($statsClasse) {
            $matiere = $notesMatiere->first()->matiere;
            $moyenne = round((float) $notesMatiere->avg('valeur'), 2);
            $classe = $statsClasse[$matiere->id] ?? null;

            return [
                'matiere' => [
                    'id' => $matiere->id,
                    'nom' => $matiere->nom,
                    'code' => $matiere->code,
                    'couleur' => $matiere->couleur,
                    'coefficient' => (float) ($matiere->coefficient ?? 1),
                ],
                'moyenne' => $moyenne,
                'moyenne_classe' => $classe['moyenne'] ?? null,
                'min' => $classe['min'] ?? null,
                'max' => $classe['max'] ?? null,
                'appreciation' => $this->appreciationNote($moyenne),
            ];
        })->values()->all();

        $moyenneGenerale = $notes->isEmpty() ? 0 : round($this->moyennePonderee($notes), 2);
        $mention = $notes->isEmpty() ? null : $this->mention($moyenneGenerale);

        return [
            'etudiant' => $etudiant->loadMissing('classe:id,nom,niveau'),
            'classe' => \App\Models\Classe::find($classeId)?->only(['id', 'nom', 'niveau']),
            'trimestre' => $trimestre,
            'matieres' => $matieres,
            'effectif' => Etudiant::where('classe_id', $classeId)->count(),
            'moyenne_generale' => $moyenneGenerale,
            'appreciation_generale' => $mention['label'] ?? '',
            'mention' => $mention,
            'decision' => $this->decision($notes->isEmpty() ? null : $moyenneGenerale),
            'rang' => $this->rang($etudiant, $trimestre, $annee, $classeId),
        ];
    }

    /**
     * Pour chaque matière : moyenne de la classe, plus petite et plus grande moyenne d'élève.
     */
    private function statsParMatiereClasse(Etudiant $etudiant, ?int $trimestre, ?string $annee = null, ?int $classeId = null): array
    {
        $ids = Etudiant::where('classe_id', $classeId ?? $etudiant->classe_id)->pluck('id');

        $parMatiere = \App\Models\Note::query()
            ->whereIn('etudiant_id', $ids)
            ->when($annee, fn ($q) => $q->where('annee_scolaire', $annee))
            ->when($trimestre, fn ($q) => $q->where('trimestre', $trimestre))
            ->get(['etudiant_id', 'matiere_id', 'valeur'])
            ->groupBy('matiere_id');

        $stats = [];
        foreach ($parMatiere as $matiereId => $notes) {
            $moyennesEleves = $notes->groupBy('etudiant_id')->map(fn ($n) => (float) $n->avg('valeur'));
            $stats[(int) $matiereId] = [
                'moyenne' => round($moyennesEleves->avg(), 2),
                'min' => round($moyennesEleves->min(), 2),
                'max' => round($moyennesEleves->max(), 2),
            ];
        }

        return $stats;
    }

    private function moyennePonderee(Collection $notes): float
    {
        $totalPoints = 0.0;
        $totalCoefficient = 0.0;

        foreach ($notes->groupBy('matiere_id') as $notesMatiere) {
            $coefficient = (float) ($notesMatiere->first()->matiere->coefficient ?? 1);
            $totalPoints += (float) $notesMatiere->avg('valeur') * $coefficient;
            $totalCoefficient += $coefficient;
        }

        return $totalCoefficient > 0 ? $totalPoints / $totalCoefficient : 0.0;
    }

    private function rang(Etudiant $etudiant, ?int $trimestre, ?string $annee = null, ?int $classeId = null): ?int
    {
        $ids = Etudiant::where('classe_id', $classeId ?? $etudiant->classe_id)->pluck('id')->all();
        $position = array_search($etudiant->id, array_keys($this->computeClassAverages($ids, $trimestre, $annee)), true);

        return $position === false ? null : $position + 1;
    }
}
