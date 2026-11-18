<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\Paiement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class FraisScolaireController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
        $this->middleware('role:admin,proviseur,gestionnaire,agent_comptable')->only(['index', 'stats', 'show', 'paiements', 'export', 'recu']);
        $this->middleware('role:admin,gestionnaire,agent_comptable')->only(['store', 'update', 'bulkInit', 'addPaiement']);
    }

    public function index(Request $request): JsonResponse
    {
        $query = FraisScolaire::query()->with('etudiant:id,nom,prenom,matricule');

        if ($request->etudiant_id) {
            $query->where('etudiant_id', $request->etudiant_id);
        }

        if ($request->annee_scolaire) {
            $query->where('annee_scolaire', $request->annee_scolaire);
        }

        if ($request->statut) {
            $query->where('statut', $request->statut);
        }

        return response()->json($query->orderByDesc('id')->paginate(20));
    }

    public function stats(Request $request): JsonResponse
    {
        // Les totaux suivent l'année affichée à l'écran, comme la liste.
        $frais = FraisScolaire::query()
            ->when($request->query('annee_scolaire'), fn ($q, $annee) => $q->where('annee_scolaire', $annee));

        $montantTotal = (float) (clone $frais)->sum('montant');
        $montantPaye = (float) Paiement::where('statut', 'completed')
            ->whereIn('frais_scolaire_id', (clone $frais)->select('id'))
            ->sum('montant');

        return response()->json([
            'montant_total' => $montantTotal,
            'montant_paye' => $montantPaye,
            'reste' => $montantTotal - $montantPaye,
            'effectif' => (clone $frais)->distinct('etudiant_id')->count('etudiant_id'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'etudiant_id' => 'required|integer|exists:etudiants,id',
            'annee_scolaire' => 'required|string|max:20',
            'montant_total' => 'required|numeric|min:0',
            'libelle' => 'nullable|string|max:255',
            'date_echeance' => 'nullable|date',
        ]);

        $frais = FraisScolaire::create($this->donneesFrais($validated));

        AuditLogger::log($request->user(), 'create', 'FraisScolaire', $frais->id, "Nouveaux frais créés pour l'élève {$frais->etudiant_id}");

        return response()->json(['frais' => $frais->load('etudiant:id,nom,prenom,matricule')], 201);
    }

    public function show(FraisScolaire $frais): JsonResponse
    {
        return response()->json($frais->load(['etudiant:id,nom,prenom,matricule', 'paiements']));
    }

    public function update(Request $request, FraisScolaire $frais): JsonResponse
    {
        $validated = $request->validate([
            'libelle' => 'nullable|string|max:255',
            'montant_total' => 'nullable|numeric|min:0',
            'date_echeance' => 'nullable|date',
            'statut' => 'nullable|in:pending,partial,paid,overdue',
        ]);

        $valeurs = fn (FraisScolaire $f) => [
            'libelle' => $f->libelle,
            'montant' => (float) $f->montant,
            'date_echeance' => (string) $f->date_echeance,
            'statut' => $f->statut,
        ];
        $avant = $valeurs($frais);
        $frais->update($this->donneesFrais($validated, $frais));

        AuditLogger::log($request->user(), 'update', 'FraisScolaire', $frais->id, "Mise à jour des frais",
            $avant, $valeurs($frais->fresh()));

        return response()->json(['frais' => $frais->fresh()->load('etudiant:id,nom,prenom,matricule')]);
    }

    public function bulkInit(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'classe_id' => 'required|integer|exists:classes,id',
            'montant_total' => 'required|numeric|min:0',
            'annee_scolaire' => 'nullable|string|max:20',
            'libelle' => 'nullable|string|max:255',
            'date_echeance' => 'nullable|date',
        ]);

        $etudiants = Etudiant::where('classe_id', $validated['classe_id'])->get();

        if ($etudiants->isEmpty()) {
            return response()->json(['message' => 'Cette classe ne contient aucun élève.'], 422);
        }

        $annee = $validated['annee_scolaire'] ?? Etablissement::value('annee_scolaire');

        $crees = DB::transaction(fn () => $etudiants->map(fn (Etudiant $etudiant) => FraisScolaire::create($this->donneesFrais([
            'etudiant_id' => $etudiant->id,
            'annee_scolaire' => $annee,
            'montant_total' => $validated['montant_total'],
            'libelle' => $validated['libelle'] ?? null,
            'date_echeance' => $validated['date_echeance'] ?? null,
        ]))));

        AuditLogger::log($request->user(), 'create', 'FraisScolaire', null,
            "Frais créés en masse pour {$crees->count()} élève(s) de la classe {$validated['classe_id']}");

        return response()->json(['message' => "Frais créés pour {$crees->count()} élève(s).", 'count' => $crees->count()], 201);
    }

    public function paiements(FraisScolaire $frais): JsonResponse
    {
        return response()->json($frais->paiements()->orderByDesc('date_paiement')->get());
    }

    public function addPaiement(Request $request, FraisScolaire $frais): JsonResponse
    {
        $validated = $request->validate([
            'montant' => 'required|numeric|min:0.01',
            'date_paiement' => 'required|date',
            'mode' => 'required|string|max:50',
            'reference' => 'nullable|string|max:100',
        ]);

        $frais->loadMissing('paiements');
        if ($validated['montant'] > $frais->reste) {
            return response()->json([
                'message' => "Le montant dépasse le reste à payer ({$frais->reste} FCFA).",
            ], 422);
        }

        $paiement = Paiement::create([
            'frais_scolaire_id' => $frais->id,
            'etudiant_id' => $frais->etudiant_id,
            'montant' => $validated['montant'],
            'date_paiement' => $validated['date_paiement'],
            'mode_paiement' => $validated['mode'],
            'reference' => $validated['reference'] ?? null,
            'statut' => 'completed',
        ]);

        AuditLogger::log($request->user(), 'create', 'Paiement', $paiement->id, "Paiement de {$paiement->montant} FCFA pour les frais {$frais->id}",
            null, [
                'frais_id' => $frais->id,
                'etudiant_id' => $paiement->etudiant_id,
                'montant' => (float) $paiement->montant,
                'mode' => $paiement->mode_paiement,
                'date' => (string) $paiement->date_paiement,
                'reference' => $paiement->reference,
            ]);

        return response()->json([
            'paiement' => $paiement,
            'frais' => $frais->fresh()->load('etudiant:id,nom,prenom,matricule'),
        ], 201);
    }

    public function export(Request $request)
    {
        $frais = FraisScolaire::with('etudiant:id,nom,prenom,matricule')
            ->when($request->query('annee_scolaire'), fn ($q, $v) => $q->where('annee_scolaire', $v))
            ->orderBy('id')
            ->get();

        $entetes = ['Matricule', 'Nom', 'Prénom', 'Année scolaire', 'Montant total', 'Payé', 'Reste', 'Statut'];
        $lignes = [];
        foreach ($frais as $f) {
            $lignes[] = [
                $f->etudiant?->matricule, $f->etudiant?->nom, $f->etudiant?->prenom, $f->annee_scolaire,
                $f->montant_total, $f->montant_paye, $f->reste,
                $f->reste <= 0 ? 'Soldé' : ($f->montant_paye > 0 ? 'Partiel' : 'Impayé'),
            ];
        }

        // Excel mis en forme par défaut ; CSV sur demande (format=csv).
        if ($request->query('format') === 'csv') {
            $csv = fopen('php://temp', 'r+');
            fwrite($csv, "\xEF\xBB\xBF");
            fputcsv($csv, $entetes, ';');
            foreach ($lignes as $ligne) {
                fputcsv($csv, $ligne, ';');
            }
            rewind($csv);
            $contenu = stream_get_contents($csv);
            fclose($csv);

            return response($contenu, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="frais-scolaires.csv"',
            ]);
        }

        $contenu = \App\Services\Xlsx::generer('Frais scolaires', $entetes, $lignes, ['Montant total', 'Payé', 'Reste'],
            implode(' — ', array_filter([$request->user()->etablissement?->nom, 'Frais scolaires'])));

        return response($contenu, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="frais-scolaires.xlsx"',
        ]);
    }

    public function recu(Paiement $paiement)
    {
        $paiement->load(['etudiant:id,nom,prenom,matricule', 'fraisScolaire']);
        $frais = $paiement->fraisScolaire;
        $etablissement = Etablissement::find($paiement->etablissement_id);
        $numero = 'REC-'.str_pad((string) $paiement->id, 6, '0', STR_PAD_LEFT);
        $e = fn ($v) => e((string) $v);

        // Identité de l'école : logo intégré (le reçu est un fichier autonome), couleur, devise et coordonnées.
        $logo = '';
        if ($etablissement?->logo_path) {
            $fichier = realpath(public_path($etablissement->logo_path));
            $dansPublic = $fichier && str_starts_with($fichier, (string) realpath(public_path()));
            if ($dansPublic && is_file($fichier) && filesize($fichier) <= 1024 * 1024) {
                $type = mime_content_type($fichier) ?: 'image/png';
                $logo = '<img src="data:'.$type.';base64,'.base64_encode((string) file_get_contents($fichier)).'" alt="" style="height:64px;margin-right:16px">';
            }
        }
        $couleur = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $etablissement?->couleur_principale) ? $etablissement->couleur_principale : '#1F5C4A';
        $coordonnees = implode(' — ', array_filter([$etablissement?->adresse, $etablissement?->telephone ? 'Tél : '.$etablissement->telephone : null]));

        $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Reçu '.$numero.'</title>'
            .'<style>body{font-family:Georgia,serif;color:#15302A;padding:40px;max-width:720px;margin:auto}'
            .'.entete{display:flex;align-items:center;border-bottom:3px solid '.$couleur.';padding-bottom:14px}'
            .'h1{font-size:22px;margin:0;color:'.$couleur.'}.petit{font-size:13px;color:#5b6b66;margin:2px 0}'
            .'table{width:100%;border-collapse:collapse;margin-top:20px}'
            .'td{padding:8px;border-bottom:1px solid #E4DED3}.montant{font-size:20px;font-weight:bold}'
            .'@media print{button{display:none}}</style></head><body>'
            .'<div class="entete">'.$logo.'<div>'
            .'<h1>'.$e($etablissement?->nom ?? 'Établissement').'</h1>'
            .($etablissement?->devise ? '<p class="petit">'.$e($etablissement->devise).'</p>' : '')
            .($coordonnees ? '<p class="petit">'.$e($coordonnees).'</p>' : '')
            .'</div></div>'
            .'<p>Reçu de paiement n° '.$numero.'</p>'
            .'<table>'
            .'<tr><td>Élève</td><td>'.$e($paiement->etudiant?->prenom.' '.$paiement->etudiant?->nom).' ('.$e($paiement->etudiant?->matricule).')</td></tr>'
            .'<tr><td>Frais</td><td>'.$e($frais?->libelle).' — '.$e($frais?->annee_scolaire).'</td></tr>'
            .'<tr><td>Date</td><td>'.$e($paiement->date_paiement?->format('d/m/Y')).'</td></tr>'
            .'<tr><td>Mode</td><td>'.$e($paiement->mode_paiement).'</td></tr>'
            .'<tr><td>Montant reçu</td><td class="montant">'.number_format((float) $paiement->montant, 0, ',', ' ').' FCFA</td></tr>'
            .'<tr><td>Reste à payer</td><td>'.number_format((float) $frais?->reste, 0, ',', ' ').' FCFA</td></tr>'
            .'</table><p style="margin-top:40px">Signature : ____________________</p>'
            .'<button onclick="window.print()">Imprimer</button></body></html>';

        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function donneesFrais(array $validated, ?FraisScolaire $existant = null): array
    {
        $donnees = collect($validated)->only(['etudiant_id', 'annee_scolaire', 'libelle', 'date_echeance', 'statut'])->all();

        if (array_key_exists('montant_total', $validated) && $validated['montant_total'] !== null) {
            $donnees['montant'] = $validated['montant_total'];
        }

        $donnees['libelle'] = $donnees['libelle'] ?? $existant?->libelle
            ?? 'Frais de scolarité '.($donnees['annee_scolaire'] ?? $existant?->annee_scolaire);

        return array_filter($donnees, fn ($valeur) => $valeur !== null);
    }
}
