<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Etudiant;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import d'élèves depuis un CSV (avec détection automatique des colonnes) et export des données.
 * L'aperçu ne modifie rien ; l'import n'écrit qu'après validation de toutes les lignes utiles.
 */
class ImportExportController extends Controller
{
    /** Synonymes acceptés pour chaque champ (sans accents, minuscules, sans espaces ni tirets). */
    private const SYNONYMES = [
        'nom' => ['nom', 'nomeleve', 'nomdefamille', 'nomfamille', 'lastname', 'surname'],
        'prenom' => ['prenom', 'prenoms', 'prenomeleve', 'firstname'],
        'classe' => ['classe', 'nomclasse', 'class', 'groupe'],
        'date_naissance' => ['datenaissance', 'datedenaissance', 'naissance', 'dob', 'datedenaiss'],
        'genre' => ['genre', 'sexe', 'gender'],
        'telephone' => ['telephone', 'tel', 'phone', 'contact'],
        'adresse' => ['adresse', 'address', 'domicile'],
    ];

    private const LIMITE_LIGNES = 2000;

    public function importerEtudiants(Request $request): JsonResponse
    {
        $request->validate([
            'fichier' => 'required|file|mimes:csv,txt,xlsx|max:5120',
            'mode' => 'required|in:apercu,importer',
            'mapping' => 'nullable|json',
        ]);

        $fichier = $request->file('fichier');
        [$entetes, $lignes, $titres] = strtolower($fichier->getClientOriginalExtension()) === 'xlsx'
            ? \App\Services\Xlsx::lire($fichier->getRealPath())
            : [...$this->lireCsv($fichier->getRealPath()), 0];

        if (empty($entetes)) {
            return response()->json(['message' => 'Le fichier est vide ou illisible.'], 422);
        }
        if (count($lignes) > self::LIMITE_LIGNES) {
            return response()->json(['message' => 'Un import est limité à '.self::LIMITE_LIGNES.' lignes.'], 422);
        }

        // Détection automatique, puis correction manuelle éventuelle choisie dans l'écran de mappage.
        $colonnes = $this->appliquerMapping($this->detecterColonnes($entetes), $request->input('mapping'), count($entetes));
        if (! isset($colonnes['nom'], $colonnes['prenom'])) {
            return response()->json([
                'message' => 'Colonnes « nom » et « prénom » introuvables dans le fichier.',
                'colonnes' => $this->apercuColonnes($entetes, $colonnes),
            ], 422);
        }

        [$valides, $erreurs] = $this->valider($lignes, $colonnes, $titres ?? 0);

        $resume = [
            'colonnes' => $this->apercuColonnes($entetes, $colonnes),
            'entetes' => $entetes,
            'champs' => $this->champsPourMappage($entetes, $colonnes, $lignes),
            'total' => count($lignes),
            'valides' => count($valides),
            'erreurs' => $erreurs,
            'apercu' => array_slice($valides, 0, 10),
        ];

        if ($request->input('mode') === 'apercu') {
            return response()->json($resume);
        }

        $importes = 0;
        $ignores = 0;
        DB::transaction(function () use ($valides, $request, &$importes, &$ignores) {
            foreach ($valides as $ligne) {
                $classe = $ligne['classe']
                    ? Classe::firstOrCreate(
                        ['etablissement_id' => $request->user()->etablissement_id, 'nom' => $ligne['classe']],
                        // « Terminale A » → niveau « Terminale » ; le niveau est obligatoire en base.
                        ['niveau' => strtok($ligne['classe'], ' ') ?: $ligne['classe']]
                    )
                    : null;

                $existe = Etudiant::where('nom', $ligne['nom'])
                    ->where('prenom', $ligne['prenom'])
                    ->when($classe, fn ($q) => $q->where('classe_id', $classe->id))
                    ->exists();

                if ($existe) {
                    $ignores++;
                    continue;
                }

                Etudiant::create([
                    'etablissement_id' => $request->user()->etablissement_id,
                    'nom' => $ligne['nom'],
                    'prenom' => $ligne['prenom'],
                    'classe_id' => $classe?->id,
                    'date_naissance' => $ligne['date_naissance'],
                    'genre' => $ligne['genre'],
                    'telephone' => $ligne['telephone'],
                    'adresse' => $ligne['adresse'],
                ]);
                $importes++;
            }
        });

        AuditLogger::log($request->user(), 'import_eleves', 'etudiants', null, "Import CSV : {$importes} élève(s) importé(s), {$ignores} doublon(s) ignoré(s).");

        return response()->json([
            'message' => "{$importes} élève(s) importé(s).",
            'importes' => $importes,
            'ignores' => $ignores,
            'erreurs' => $erreurs,
        ], 201);
    }

    /**
     * Liste des élèves (Excel ou CSV) : mêmes filtres que l'écran, colonnes fixes pour le suivi administratif.
     * Statut = inscrit cette année ; contact du tuteur = premier parent rattaché à l'élève.
     */
    public function exporterEtudiants(Request $request)
    {
        $etudiants = \App\Services\FiltreEleves::appliquer(
            Etudiant::query()
                ->with(['classe:id,nom', 'parents:id,name,telephone,email'])
                ->withExists(['inscriptions as est_inscrit' => \App\Services\FiltreEleves::inscritCetteAnnee($request)]),
            $request
        )->orderBy('nom')->orderBy('prenom')->get();

        $lignes = $etudiants->map(function ($e) {
            $tuteur = $e->parents->first();

            return [
                $e->matricule,
                $e->nom,
                $e->prenom,
                match ($e->genre) { 'M' => 'Masculin', 'F' => 'Féminin', default => '' },
                $e->classe?->nom,
                $e->est_inscrit ? 'Actif' : 'Inactif',
                $tuteur?->telephone ?? '',
                $tuteur?->email ?? '',
            ];
        })->all();

        return $this->exporter($request, 'eleves', 'Élèves',
            ['Matricule', 'Nom', 'Prénom', 'Sexe', 'Classe', 'Statut', 'Contact Tuteur', 'Email Tuteur'], $lignes);
    }

    /** Exporte en Excel (format=xlsx, par défaut) ou en CSV (format=csv), avec une mise en forme soignée. */
    private function exporter(Request $request, string $nomFichier, string $feuille, array $entetes, array $lignes, array $colonnesMontant = [])
    {
        if ($request->query('format') === 'csv') {
            return $this->csv($nomFichier.'.csv', $entetes, $lignes);
        }

        $contenu = \App\Services\Xlsx::generer($feuille, $entetes, $lignes, $colonnesMontant,
            implode(' — ', array_filter([$request->user()->etablissement?->nom, $feuille])));

        return response($contenu, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$nomFichier.'.xlsx"',
        ]);
    }

    /** Modèle Excel vierge à remplir pour importer les élèves : mêmes intitulés que ceux reconnus à l'import. */
    public function modeleEleves(Request $request)
    {
        $contenu = \App\Services\Xlsx::generer(
            'Élèves',
            ['Nom', 'Prénom', 'Classe', 'Date de naissance', 'Genre', 'Téléphone', 'Adresse'],
            [],
            [],
            'Import des élèves : une ligne par élève, dates au format jj/mm/aaaa, genre M ou F. Supprimez cette ligne d’explication avant l’import.'
        );

        return response($contenu, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="modele-import-eleves.xlsx"',
        ]);
    }

    public function exporterNotes(Request $request)
    {
        $lignes = DB::table('notes')
            ->join('etudiants', 'etudiants.id', '=', 'notes.etudiant_id')
            ->join('matieres', 'matieres.id', '=', 'notes.matiere_id')
            ->leftJoin('classes', 'classes.id', '=', 'etudiants.classe_id')
            ->where('etudiants.etablissement_id', $request->user()->etablissement_id)
            ->orderBy('classes.nom')->orderBy('etudiants.nom')->orderBy('notes.trimestre')
            ->get(['etudiants.matricule', 'etudiants.nom', 'etudiants.prenom', 'classes.nom as classe',
                'matieres.nom as matiere', 'notes.trimestre', 'notes.type', 'notes.valeur']);

        return $this->exporter($request, 'notes', 'Notes', ['Matricule', 'Nom', 'Prénom', 'Classe', 'Matière', 'Trimestre', 'Type', 'Note /20'],
            $lignes->map(fn ($l) => [$l->matricule, $l->nom, $l->prenom, $l->classe, $l->matiere, $l->trimestre, $l->type, (float) $l->valeur])->all(),
            ['Note /20']);
    }

    /** @return array{0: array, 1: array} en-têtes et lignes (tableaux associatifs bruts) */
    private function lireCsv(string $chemin): array
    {
        $contenu = file_get_contents($chemin);
        if (! mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }
        $contenu = ltrim($contenu, "\xEF\xBB\xBF");

        $premiereLigne = strtok($contenu, "\n") ?: '';
        $separateur = substr_count($premiereLigne, ';') >= substr_count($premiereLigne, ',') ? ';' : ',';

        $flux = fopen('php://temp', 'r+');
        fwrite($flux, $contenu);
        rewind($flux);

        $entetes = fgetcsv($flux, 0, $separateur) ?: [];
        $lignes = [];
        while (($cellules = fgetcsv($flux, 0, $separateur)) !== false) {
            if (count(array_filter($cellules, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }
            $lignes[] = $cellules;
        }
        fclose($flux);

        return [array_map(fn ($e) => trim((string) $e), $entetes), $lignes];
    }

    /** Associe chaque champ attendu à l'index de sa colonne dans le fichier. */
    private function detecterColonnes(array $entetes): array
    {
        $colonnes = [];
        foreach ($entetes as $index => $entete) {
            $cle = $this->normaliser($entete);
            foreach (self::SYNONYMES as $champ => $synonymes) {
                if (in_array($cle, $synonymes, true) && ! isset($colonnes[$champ])) {
                    $colonnes[$champ] = $index;
                }
            }
        }

        // Reconnaissance approximative : « Nom de l'élève » ou « Prenoms » (fautes de frappe) sont acceptés.
        foreach (self::SYNONYMES as $champ => $synonymes) {
            if (isset($colonnes[$champ])) {
                continue;
            }
            foreach ($entetes as $index => $entete) {
                $cle = $this->normaliser($entete);
                if ($cle === '' || in_array($index, $colonnes, true)) {
                    continue;
                }
                foreach ($synonymes as $synonyme) {
                    if (strlen($synonyme) >= 4 && levenshtein($cle, $synonyme) <= 2) {
                        $colonnes[$champ] = $index;
                        break 3;
                    }
                }
            }
        }

        return $colonnes;
    }

    /** Applique le mappage choisi à l'écran : { champ: index de colonne }, ou null pour ignorer le champ. */
    private function appliquerMapping(array $detectees, ?string $mapping, int $nbColonnes): array
    {
        if ($mapping === null || $mapping === '') {
            return $detectees;
        }

        $choisis = json_decode($mapping, true) ?: [];
        foreach (array_keys(self::SYNONYMES) as $champ) {
            if (! array_key_exists($champ, $choisis)) {
                continue;
            }
            $index = $choisis[$champ];
            if ($index === null || $index === '') {
                unset($detectees[$champ]);
            } elseif (is_numeric($index) && (int) $index >= 0 && (int) $index < $nbColonnes) {
                $detectees[$champ] = (int) $index;
            }
        }

        return $detectees;
    }

    private function apercuColonnes(array $entetes, array $colonnes): array
    {
        $parIndex = array_flip($colonnes);

        return array_map(fn ($entete, $index) => [
            'entete' => $entete,
            'champ' => $parIndex[$index] ?? null,
        ], $entetes, array_keys($entetes));
    }

    /** Liste des champs attendus, avec la colonne retenue et un exemple de valeur, pour l'écran de mappage. */
    private function champsPourMappage(array $entetes, array $colonnes, array $lignes): array
    {
        $libelles = [
            'nom' => 'Nom', 'prenom' => 'Prénom', 'classe' => 'Classe', 'date_naissance' => 'Date de naissance',
            'genre' => 'Genre', 'telephone' => 'Téléphone', 'adresse' => 'Adresse',
        ];

        return array_map(fn ($champ) => [
            'champ' => $champ,
            'libelle' => $libelles[$champ],
            'obligatoire' => in_array($champ, ['nom', 'prenom'], true),
            'colonne' => $colonnes[$champ] ?? null,
            'entete' => isset($colonnes[$champ]) ? ($entetes[$colonnes[$champ]] ?? null) : null,
            'exemple' => isset($colonnes[$champ]) ? ($lignes[0][$colonnes[$champ]] ?? null) : null,
        ], array_keys(self::SYNONYMES));
    }

    /** @return array{0: array, 1: array} lignes valides normalisées et erreurs (ligne, message) */
    private function valider(array $lignes, array $colonnes, int $titres = 0): array
    {
        $valides = [];
        $erreurs = [];

        foreach ($lignes as $numero => $cellules) {
            $ligne = $numero + 2 + $titres; // numéro de la ligne dans le tableur (1 = en-tête, après les lignes de titre)
            $valeur = fn (string $champ) => isset($colonnes[$champ]) ? trim((string) ($cellules[$colonnes[$champ]] ?? '')) : '';

            $nom = $valeur('nom');
            $prenom = $valeur('prenom');
            if ($nom === '' || $prenom === '') {
                $erreurs[] = ['ligne' => $ligne, 'message' => 'Nom ou prénom manquant.'];
                continue;
            }

            $date = null;
            if ($valeur('date_naissance') !== '') {
                try {
                    $date = $this->parserDate($valeur('date_naissance'));
                } catch (\Throwable) {
                    $erreurs[] = ['ligne' => $ligne, 'message' => "Date de naissance invalide : « {$valeur('date_naissance')} »."];
                    continue;
                }
            }

            $valides[] = [
                'nom' => mb_substr($nom, 0, 255),
                'prenom' => mb_substr($prenom, 0, 255),
                'classe' => $valeur('classe') !== '' ? mb_substr($valeur('classe'), 0, 100) : null,
                'date_naissance' => $date,
                'genre' => $this->genre($valeur('genre')),
                'telephone' => $valeur('telephone') ?: null,
                'adresse' => $valeur('adresse') ?: null,
            ];
        }

        return [$valides, $erreurs];
    }

    private function parserDate(string $texte): string
    {
        // Excel stocke une date sous forme de nombre de jours : 1 = 01/01/1900 (cellule au format date).
        $texte = trim($texte);
        if (preg_match('/^\d{1,6}$/', $texte) && (int) $texte >= 1 && (int) $texte <= 2958465) {
            $jours = (int) $texte;
            // Excel considère à tort 1900 comme bissextile : au-delà du 28/02/1900, on corrige d'un jour.
            if ($jours > 60) {
                $jours--;
            }

            return Carbon::create(1899, 12, 31)->addDays($jours)->toDateString();
        }

        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y'];
        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $texte);
            } catch (\Throwable) {
                continue;
            }
            if ($date && $date->format($format) === $texte) {
                return $date->toDateString();
            }
        }

        throw new \InvalidArgumentException('format inconnu');
    }

    private function genre(string $valeur): ?string
    {
        return match ($this->normaliser($valeur)) {
            'm', 'masculin', 'garcon', 'h' => 'M',
            'f', 'feminin', 'fille' => 'F',
            default => null,
        };
    }

    private function normaliser(string $texte): string
    {
        $sansAccents = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texte) ?: $texte;

        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $sansAccents));
    }

    private function csv(string $nomFichier, array $entetes, array $lignes): StreamedResponse
    {
        return response()->streamDownload(function () use ($entetes, $lignes) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, $entetes, ';');
            foreach ($lignes as $ligne) {
                fputcsv($sortie, $ligne, ';');
            }
            fclose($sortie);
        }, $nomFichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
