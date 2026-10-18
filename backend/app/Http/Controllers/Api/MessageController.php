<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Services\AuditLogger;

class MessageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function stats(Request $request)
    {
        $userId = auth()->id();
        $nonLus = Message::where('recipient_id', $userId)->where('read', false)->count();

        return response()->json([
            'non_lus' => $nonLus,
            'unread_count' => $nonLus,
            'total_inbox' => Message::where('recipient_id', $userId)->count(),
            'total_sent' => Message::where('sender_id', $userId)->count(),
        ]);
    }

    public function index(Request $request)
    {
        $messages = Message::where('recipient_id', auth()->id())
            ->with(['sender', 'classe'])
            ->latest()
            ->paginate(20);

        return response()->json($messages);
    }

    public function inbox(Request $request)
    {
        $messages = Message::where('recipient_id', auth()->id())
            ->with(['sender', 'classe'])
            ->latest()
            ->paginate(20);

        return response()->json($messages);
    }

    public function sent(Request $request)
    {
        $messages = Message::where('sender_id', auth()->id())
            ->with(['recipient', 'classe'])
            ->latest()
            ->paginate(20);

        return response()->json($messages);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'nullable|in:annonce,devoir,examen,sujet',
            'cible' => 'nullable|in:enseignants,administration',
            'titre' => 'required|string|max:255',
            'contenu' => 'required|string',
            'recipient_id' => 'nullable|integer|exists:users,id',
            'fichier' => 'nullable|file|max:5120|mimes:pdf,doc,docx,jpg,jpeg,png',
        ]);

        $sender = $request->user();
        $destinataires = $this->destinataires($sender, $validated);

        if ($destinataires->isEmpty()) {
            return response()->json(['message' => 'Aucun destinataire trouvé.'], 422);
        }

        $fichier = $request->file('fichier');
        $fichierPath = $fichier?->store('messages', 'local');

        $messages = DB::transaction(fn () => $destinataires->map(fn (User $destinataire) => Message::create([
            'sender_id' => $sender->id,
            'recipient_id' => $destinataire->id,
            'subject' => $validated['titre'],
            'body' => $validated['contenu'],
            'type' => $validated['type'] ?? 'annonce',
            'fichier_path' => $fichierPath,
            'fichier_nom' => $fichier?->getClientOriginalName(),
            'etablissement_id' => $sender->etablissement_id,
        ])));

        AuditLogger::log($sender, 'create', 'Message', $messages->first()->id,
            "Message « {$validated['titre']} » envoyé à {$messages->count()} destinataire(s)");

        return response()->json([
            'message' => "Message envoyé à {$messages->count()} destinataire(s).",
            'count' => $messages->count(),
        ], 201);
    }

    public function fichier(Message $message)
    {
        $user = auth()->user();

        if ($message->sender_id !== $user->id && $message->recipient_id !== $user->id) {
            abort(403, "Vous n'avez pas accès à cette pièce jointe.");
        }

        if (! $message->fichier_path) {
            abort(404, 'Aucune pièce jointe pour ce message.');
        }

        return Storage::disk('local')->download($message->fichier_path, $message->fichier_nom);
    }

    private function destinataires(User $sender, array $validated): Collection
    {
        if (! empty($validated['recipient_id'])) {
            return User::whereKey($validated['recipient_id'])
                ->where('etablissement_id', $sender->etablissement_id)
                ->get();
        }

        return match ($validated['cible'] ?? null) {
            'enseignants' => $this->peutDiffuserAuxEnseignants($sender)
                ? User::where('role', 'enseignant')->where('etablissement_id', $sender->etablissement_id)->get()
                : abort(403, "Les enseignants ne peuvent pas diffuser à l'ensemble des enseignants."),
            'administration' => User::where('role', 'admin')->where('etablissement_id', $sender->etablissement_id)->get(),
            default => collect(),
        };
    }

    private function peutDiffuserAuxEnseignants(User $sender): bool
    {
        return $sender->role !== 'enseignant';
    }

    public function show(Message $message)
    {
        if ($message->recipient_id === auth()->id()) {
            $message->markAsRead();
        }

        return response()->json($message->load(['sender', 'recipient']));
    }

    public function markAsRead(Message $message)
    {
        if ($message->recipient_id === auth()->id()) {
            $message->markAsRead();
        }

        return response()->json($message);
    }

    public function delete(Message $message)
    {
        if ($message->sender_id !== auth()->id() && $message->recipient_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $message->delete();
        return response()->json(null, 204);
    }
}
