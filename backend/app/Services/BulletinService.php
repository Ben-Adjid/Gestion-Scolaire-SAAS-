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

    public function moyenneAnnuelle(Etudiant $etudiant): ?float
    {
        $notes = $etudiant->notes()->with('matiere')->get();

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
    public function computeClassAverages(array $etudiantIds, ?int $trimestre = 1): array
    {
        if ($etudiantIds === []) {
            return [];
        }

        $notes = \App\Models\Note::query()
            ->whereIn('etudiant_id', $etudiantIds)
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
        $notes = $etudiant->notes()
            ->when($trimestre, fn ($q) => $q->where('trimestre', $trimestre))
            ->with('matiere')
            ->get();

        $matieres = $notes->groupBy('matiere_id')->map(function (Collection $notesMatiere) {
            $matiere = $notesMatiere->first()->matiere;
            $coefficient = (float) ($matiere->coefficient ?? 1);
            $moyenne = round((float) $notesMatiere->avg('valeur'), 2);

            return [
                'nom' => $matiere->nom,
                'code' => $matiere->code,
                'note' => $moyenne,
                'appreciation' => $notesMatiere->last()->appreciation,
                'coefficient' => $coefficient,
                'points' => round($moyenne * $coefficient, 2),
            ];
        })->values()->all();

        $moyenneGenerale = $notes->isEmpty() ? 0 : round($this->moyennePonderee($notes), 2);
        $mention = $notes->isEmpty() ? null : $this->mention($moyenneGenerale);

        return [
            'etudiant' => $etudiant,
            'trimestre' => $trimestre,
            'matieres' => $matieres,
            'moyenne_generale' => $moyenneGenerale,
            'appreciation_generale' => $mention['label'] ?? '',
            'mention' => $mention,
            'decision' => $this->decision($notes->isEmpty() ? null : $moyenneGenerale),
            'rang' => $this->rang($etudiant, $trimestre),
        ];
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

    private function rang(Etudiant $etudiant, ?int $trimestre): ?int
    {
        $ids = Etudiant::where('classe_id', $etudiant->classe_id)->pluck('id')->all();
        $position = array_search($etudiant->id, array_keys($this->computeClassAverages($ids, $trimestre)), true);

        return $position === false ? null : $position + 1;
    }
}
