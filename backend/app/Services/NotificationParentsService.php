<?php

namespace App\Services;

use App\Mail\NotificationParent;
use App\Models\Absence;
use App\Models\Convocation;
use App\Models\Etudiant;
use App\Models\NotificationEnvoyee;
use App\Models\User;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Prévient les parents des absences, retards et convocations de leur enfant.
 * - Réservé aux formules qui incluent « notifications_parents » (VIP et plus).
 * - Canaux : e-mail (SMTP, Brevo une fois configuré) et WhatsApp (si configuré).
 * - Un événement n'est notifié qu'une fois par parent et par canal (journal notifications_envoyees).
 * - Un échec d'envoi n'interrompt jamais l'enregistrement de l'absence.
 */
class NotificationParentsService
{
    public function __construct(private WhatsAppService $whatsapp) {}

    public function notifierAbsence(Etudiant $etudiant, Absence $absence): void
    {
        $estRetard = $absence->type === 'retard';
        $nature = $estRetard ? 'un retard' : 'une absence';
        $titre = ($estRetard ? 'Retard' : 'Absence')." de {$etudiant->prenom} {$etudiant->nom}";
        $date = Carbon::parse($absence->date)->format('d/m/Y');

        // Référence stable : un même élève, un même jour, une même nature n'est notifié qu'une fois,
        // même si l'appel est corrigé (les absences sont recréées à chaque enregistrement).
        $reference = (int) sprintf('%u', crc32("{$etudiant->id}|".Carbon::parse($absence->date)->toDateString()."|{$absence->type}"));

        $this->diffuser(
            $etudiant,
            $estRetard ? 'retard' : 'absence',
            $reference,
            $titre,
            "Bonjour, {$etudiant->prenom} {$etudiant->nom} a {$nature} le {$date}. "
                ."Si vous avez une justification, vous pouvez la transmettre depuis votre espace parent.",
        );
    }

    public function notifierConvocation(Etudiant $etudiant, Convocation $convocation): void
    {
        $date = Carbon::parse($convocation->date)->format('d/m/Y');

        $this->diffuser(
            $etudiant,
            'convocation',
            $convocation->id,
            "Convocation de {$etudiant->prenom} {$etudiant->nom}",
            "Bonjour, l'établissement vous convoque au sujet de {$etudiant->prenom} {$etudiant->nom} "
                ."le {$date}. Motif : {$convocation->motif}.",
        );
    }

    private function diffuser(Etudiant $etudiant, string $type, int $reference, string $sujet, string $texte): void
    {
        if (! in_array('notifications_parents', $etudiant->etablissement?->fonctionnalites() ?? [], true)) {
            return;
        }

        $parents = User::withoutGlobalScopes()
            ->where('role', 'parent')
            ->whereIn('id', DB::table('parent_etudiant')->where('etudiant_id', $etudiant->id)->pluck('user_id'))
            ->get();

        foreach ($parents as $parent) {
            if ($parent->email) {
                $this->envoyer($etudiant, $parent, 'email', $type, $reference, function () use ($parent, $sujet, $texte) {
                    Mail::to($parent->email)->queue(new NotificationParent($sujet, $texte));

                    return true;
                });
            }

            if ($parent->telephone) {
                $this->envoyer($etudiant, $parent, 'whatsapp', $type, $reference,
                    fn () => $this->whatsapp->envoyer($parent->telephone, $texte));
            }
        }
    }

    private function envoyer(Etudiant $etudiant, User $destinataire, string $canal, string $type, int $reference, Closure $envoi): void
    {
        $cle = ['type' => $type, 'reference_id' => $reference, 'canal' => $canal, 'destinataire_id' => $destinataire->id];

        if (NotificationEnvoyee::where($cle)->exists()) {
            return;
        }

        try {
            // Sans identifiants WhatsApp, l'envoi renvoie false : rien n'est journalisé, la notification sera retentée.
            if (! $envoi()) {
                return;
            }
            $statut = 'envoyee';
            $erreur = null;
        } catch (\Throwable $e) {
            Log::warning('Notification parent en échec.', ['canal' => $canal, 'erreur' => $e->getMessage()]);
            $statut = 'echec';
            $erreur = mb_substr($e->getMessage(), 0, 250);
        }

        NotificationEnvoyee::create($cle + [
            'etablissement_id' => $etudiant->etablissement_id,
            'statut' => $statut,
            'erreur' => $erreur,
        ]);
    }
}
