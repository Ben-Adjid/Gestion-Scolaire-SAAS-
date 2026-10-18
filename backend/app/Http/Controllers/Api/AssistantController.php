<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Note;
use App\Services\BulletinService;
use App\Services\TeacherScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AssistantController extends Controller
{
    public function chat(Request $request): JsonResponse
    {
        $message = strtolower(trim((string) $request->input('message', '')));

        if ($message === '') {
            return response()->json(['reply' => 'Je suis prêt ! Que puis-je faire pour vous ?']);
        }

        if ($this->hasAny($message, ['bonjour', 'salut', 'hello'])) {
            return response()->json(['reply' => $this->welcome()]);
        }

        if ($this->hasAny($message, ['aide', 'help', 'quoi', 'commande', 'que faire'])) {
            return response()->json(['reply' => $this->capabilities()]);
        }

        if ($this->hasAny($message, ['statistique', 'stat', 'effectif', 'nombre d', 'combien'])) {
            return response()->json(['reply' => $this->stats()]);
        }

        if ($this->hasAny($message, ['top', 'meilleur', 'palmarès', 'palmares', 'mérite', 'merite', 'premier'])) {
            return response()->json(['reply' => $this->topEleves()]);
        }

        if ($this->hasAny($message, ['appréciation', 'appreciation', 'commentaire'])) {
            return response()->json(['reply' => $this->appreciation($message)]);
        }

        if ($this->hasAny($message, ['règle', 'regle', 'seuil', 'admission', 'paramètre', 'parametre', 'mention', 'validation', 'valider'])) {
            return response()->json(['reply' => $this->regles()]);
        }

        if ($this->hasAny($message, ['moyenne annuelle', 'annuel', 'bilan annuel'])) {
            return response()->json(['reply' => "La moyenne annuelle est la moyenne des 3 trimestres, pondérée par les coefficients des matières. Ouvrez l'onglet « Bulletins » et choisissez « Bilan annuel » pour l'afficher."]);
        }

        if ($this->hasAny($message, ['emploi du temps', 'emploi temps', 'edt', 'cours'])) {
            return response()->json(['reply' => "L'emploi du temps se trouve dans le menu « Emploi du temps ». Vous pouvez le consulter par classe ou par enseignant, et le modifier si vous êtes administrateur ou censeur."]);
        }

        if ($this->hasAny($message, ['inscri', 'inscription'])) {
            return response()->json(['reply' => "Pour inscrire un élève : menu « Inscriptions ». Choisissez « Nouvel élève » (dossier complet) ou « Réinscrire un ancien élève »."]);
        }

        return response()->json(['reply' => $this->fallback($message)]);
    }

    private function hasAny(string $message, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($message, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function welcome(): string
    {
        return "Bonjour ! Je suis l'assistant de Gestion Scolaire. Je peux vous aider avec :\n\n".$this->capabilities();
    }

    private function capabilities(): string
    {
        return "• « Statistiques » : effectifs et aperçu de l'établissement\n".
            "• « Meilleurs élèves » : le palmarès du trimestre\n".
            "• « Appréciation 15 » : générer une appréciation pour une moyenne\n".
            "• « Emploi du temps » : aide sur la programmation des cours\n".
            "• « Inscription » : aide sur les inscriptions\n".
            "• « Moyenne annuelle » : explication du bilan annuel";
    }

    private function stats(): string
    {
        return "Voici un aperçu de l'établissement :\n\n".
            "• Étudiants : ".Etudiant::count()."\n".
            "• Classes : ".Classe::count()."\n".
            "• Enseignants : ".Enseignant::count()."\n".
            "• Matières : ".Matiere::count()."\n".
            "• Notes saisies : ".Note::count();
    }

    private function topEleves(): string
    {
        $classeIds = TeacherScope::classeIds(auth()->user());
        $etudiants = Etudiant::with('classe:id,nom')
            ->when($classeIds !== null, fn ($q) => $q->whereIn('classe_id', $classeIds))
            ->get();
        $averages = app(BulletinService::class)->computeClassAverages(
            $etudiants->pluck('id')->toArray(),
            1
        );

        $service = app(BulletinService::class);
        $lines = ['Voici le top des élèves (1er trimestre) :', ''];

        $i = 0;
        foreach ($averages as $id => $moyenne) {
            $etudiant = $etudiants->firstWhere('id', $id);
            if (! $etudiant) {
                continue;
            }
            $i++;
            $mention = $service->mention($moyenne);
            $lines[] = ($i <= 3 ? ['🥇', '🥈', '🥉'][$i - 1].' ' : $i.'ᵉ ').
                $etudiant->prenom.' '.$etudiant->nom.
                ' ('.$moyenne.'/20'.($mention ? ' — '.$mention['label'] : '').')';
            if ($i >= 5) {
                break;
            }
        }

        return implode("\n", $lines);
    }

    private function appreciation(string $message): string
    {
        preg_match('/(\d{1,2}(?:[.,]\d{1,2})?)/', $message, $matches);
        $valeur = isset($matches[1]) ? (float) str_replace(',', '.', $matches[1]) : null;

        if ($valeur === null) {
            return "Donnez-moi une moyenne (ex. « Appréciation 15 ») et je génère une appréciation adaptée.";
        }

        $valeur = max(0, min(20, $valeur));

        $appreciation = match (true) {
            $valeur >= 16 => "Excellent travail, résultats remarquables. Poursuivez ainsi !",
            $valeur >= 14 => "Très bon niveau, continuez vos efforts.",
            $valeur >= 12 => "Bon travail, avec un peu plus de rigueur vous ferez mieux.",
            $valeur >= 10 => "Résultats corrects mais des progrès sont attendus.",
            default => "Résultats insuffisants, un accompagnement est nécessaire.",
        };

        return "Pour une moyenne de {$valeur}/20 :\n\n« {$appreciation} »";
    }

    private function regles(): string
    {
        $t = app(BulletinService::class)->thresholds();

        return "Voici les règles de gestion en vigueur :\n\n".
            "• Barème des notes : 0 à {$t['note_max']}\n".
            "• Seuil d'admission (passe) : {$t['seuil_admission']}/20\n".
            "• Félicitations : moyenne ≥ {$t['felicitations']}\n".
            "• Tableau d'honneur : moyenne ≥ {$t['honneur']}\n".
            "• Encouragements : moyenne ≥ {$t['encouragements']}\n\n".
            "⚠️ Une note validée est verrouillée : l'enseignant ne peut plus la modifier ni la supprimer.\n".
            "Seul un administrateur ou un censeur peut la corriger.";
    }

    private function fallback(string $message): string
    {
        if (config('services.openai.api_key')) {
            return $this->openAi($message);
        }

        return "Je n'ai pas compris. Tapez « aide » pour voir ce que je sais faire.";
    }

    private function openAi(string $message): string
    {
        try {
            $response = Http::withToken(config('services.openai.api_key'))
                ->timeout(20)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => config('services.openai.model', 'gpt-4o-mini'),
                    'messages' => [
                        ['role' => 'system', 'content' => "Tu es l'assistant d'une application de gestion scolaire. Réponds de façon concise et en français."],
                        ['role' => 'user', 'content' => $message],
                    ],
                ]);

            if ($response->successful()) {
                return $response->json('choices.0.message.content') ?? 'Aucune réponse.';
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return "L'assistant IA n'est pas disponible pour le moment. Tapez « aide » pour les commandes locales.";
    }
}
