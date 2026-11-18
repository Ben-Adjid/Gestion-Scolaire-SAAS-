<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Etudiant;
use App\Models\FraisScolaire;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ConformiteController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,proviseur,secretaire,censeur,cpe')->only(['exportEleve']);
        $this->middleware('role:admin,proviseur,gestionnaire,agent_comptable')->only(['relances', 'relancerIndividuellement']);
    }

    /**
     * Toutes les données personnelles d'un élève, à remettre à la personne concernée (droit d'accès).
     */
    public function exportEleve(Request $request, Etudiant $etudiant): JsonResponse
    {
        $etudiant->load(['classe:id,nom,niveau', 'inscriptions', 'dossierInscription', 'notes.matiere:id,nom', 'absences', 'convocations']);
        $frais = FraisScolaire::where('etudiant_id', $etudiant->id)->with('paiements')->get();

        AuditLogger::log($request->user(), 'export_rgpd', 'Etudiant', $etudiant->id, "Export des données personnelles de l'élève");

        return response()->json([
            'genere_le' => now()->toIso8601String(),
            'eleve' => $etudiant->only(['matricule', 'nom', 'prenom', 'date_naissance', 'genre', 'email', 'telephone', 'adresse', 'annee_scolaire']),
            'classe' => $etudiant->classe?->only(['nom', 'niveau']),
            'inscriptions' => $etudiant->inscriptions,
            'dossier' => $etudiant->dossierInscription,
            'notes' => $etudiant->notes->map(fn ($n) => [
                'matiere' => $n->matiere?->nom, 'trimestre' => $n->trimestre, 'type' => $n->type,
                'valeur' => $n->valeur, 'appreciation' => $n->appreciation,
            ]),
            'absences' => $etudiant->absences->map(fn ($a) => ['date' => $a->date, 'justifie' => $a->justifie, 'motif' => $a->motif]),
            'convocations' => $etudiant->convocations->map(fn ($c) => ['date' => $c->date, 'motif' => $c->motif]),
            'frais' => $frais->map(fn ($f) => [
                'libelle' => $f->libelle, 'annee_scolaire' => $f->annee_scolaire, 'montant' => $f->montant,
                'paiements' => $f->paiements->map(fn ($p) => ['date' => $p->date_paiement, 'montant' => $p->montant, 'mode' => $p->mode_paiement]),
            ]),
        ]);
    }

    /**
     * Frais dont le reste à payer est positif, les plus en retard en premier.
     */
    public function relances(Request $request): JsonResponse
    {
        $frais = FraisScolaire::with('etudiant:id,nom,prenom,matricule,classe_id', 'paiements')
            ->get()
            ->filter(fn (FraisScolaire $f) => $f->reste > 0)
            ->map(fn (FraisScolaire $f) => [
                'id' => $f->id,
                'etudiant' => $f->etudiant?->only(['id', 'nom', 'prenom', 'matricule']),
                'annee_scolaire' => $f->annee_scolaire,
                'libelle' => $f->libelle,
                'reste' => $f->reste,
                'montant_paye' => $f->montant_paye,
                'date_echeance' => $f->date_echeance?->toDateString(),
                'jours_de_retard' => $f->date_echeance && $f->date_echeance->isPast() ? $f->date_echeance->diffInDays(now()) : 0,
            ])
            ->sortByDesc('jours_de_retard')
            ->values();

        return response()->json(['total' => $frais->count(), 'montant_du' => $frais->sum('reste'), 'relances' => $frais]);
    }

    public function relancerIndividuellement(Request $request, FraisScolaire $frais): JsonResponse
    {
        abort_if($frais->reste <= 0, 422, 'Ces frais sont déjà réglés.');

        $etudiant = $frais->etudiant;
        abort_unless($etudiant, 404);

        $parents = User::withoutGlobalScopes()
            ->where('role', 'parent')
            ->where('etablissement_id', $request->user()->etablissement_id)
            ->whereIn('id', DB::table('parent_etudiant')->where('etudiant_id', $etudiant->id)->pluck('user_id'))
            ->get();

        $destinataires = $parents->filter(fn (User $parent) => $parent->email || $parent->telephone);
        if ($destinataires->isEmpty()) {
            return response()->json([
                'message' => 'Aucun parent associé ne possède une adresse e-mail ou un téléphone de contact.',
                'code' => 'contact_parent_indisponible',
            ], 422);
        }

        $type = 'relance_frais_'.$frais->id.'_'.today()->format('Ymd');
        $reste = number_format($frais->reste, 0, ',', ' ');
        $sujet = "Rappel de paiement — {$etudiant->prenom} {$etudiant->nom}";
        $texte = "Bonjour, un solde de {$reste} FCFA reste dû pour {$etudiant->prenom} {$etudiant->nom} "
            ."au titre de {$frais->libelle} ({$frais->annee_scolaire}). "
            .'Merci de contacter le service de gestion pour toute question.';
        $envoyees = 0;
        $echecs = 0;
        $ignorees = 0;

        foreach ($destinataires as $parent) {
            foreach (['email', 'whatsapp'] as $canal) {
                if ($canal === 'email' && ! $parent->email) {
                    continue;
                }
                if ($canal === 'whatsapp' && ! $parent->telephone) {
                    continue;
                }

                $cle = [
                    'type' => $type,
                    'reference_id' => $frais->id,
                    'canal' => $canal,
                    'destinataire_id' => $parent->id,
                ];
                $reservee = DB::table('notifications_envoyees')->insertOrIgnore($cle + [
                    'etablissement_id' => $request->user()->etablissement_id,
                    'statut' => 'en_cours',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if ($reservee === 0) {
                    $ignorees++;
                    continue;
                }

                try {
                    if ($canal === 'email') {
                        Mail::to($parent->email)->queue(new \App\Mail\NotificationParent($sujet, $texte));
                        $success = true;
                    } else {
                        $success = app(\App\Services\WhatsAppService::class)->envoyer($parent->telephone, $texte);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Relance de frais en échec.', [
                        'frais_id' => $frais->id,
                        'destinataire_id' => $parent->id,
                        'canal' => $canal,
                        'erreur' => $e->getMessage(),
                    ]);
                    $success = false;
                }

                if (! $success) {
                    DB::table('notifications_envoyees')->where($cle)->delete();
                    $echecs++;
                    continue;
                }

                DB::table('notifications_envoyees')->where($cle)->update([
                    'statut' => 'envoyee',
                    'updated_at' => now(),
                ]);
                $envoyees++;
            }
        }

        if ($envoyees === 0 && $echecs > 0) {
            return response()->json([
                'message' => 'Aucun rappel n’a pu être envoyé. Vérifiez la configuration des canaux de communication.',
                'envoyees' => 0,
                'echecs' => $echecs,
            ], 502);
        }

        AuditLogger::log($request->user(), 'relance_frais', 'FraisScolaire', $frais->id,
            "Rappel individuel envoyé par {$envoyees} canal(aux) à {$etudiant->prenom} {$etudiant->nom}.");

        return response()->json([
            'message' => $envoyees > 0 ? 'Rappel envoyé aux contacts disponibles.' : 'Un rappel a déjà été envoyé aujourd’hui.',
            'envoyees' => $envoyees,
            'echecs' => $echecs,
            'ignorees' => $ignorees,
        ]);
    }
}
