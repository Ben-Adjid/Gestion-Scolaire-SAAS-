<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Préférence du parent : recevoir (ou non) les reçus de paiement par e-mail. */
class PreferenceRecuController extends Controller
{
    public function enregistrer(Request $request): JsonResponse
    {
        $data = $request->validate(['recevoir' => 'required|boolean']);

        $request->user()->update(['recevoir_recu_email' => $data['recevoir']]);

        return response()->json([
            'recevoir_recu_email' => (bool) $data['recevoir'],
            'message' => $data['recevoir']
                ? 'Vous recevrez vos reçus de paiement par e-mail.'
                : 'Vous ne recevrez plus vos reçus de paiement par e-mail.',
        ]);
    }
}
