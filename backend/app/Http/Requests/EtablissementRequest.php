<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EtablissementRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'pays' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(config('pays.liste')))],
            'devise' => ['nullable', 'string', 'max:255'],
            'ministere' => ['nullable', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'ville' => ['nullable', 'string', 'max:120'],
            'responsable' => ['nullable', 'string', 'max:255'],
            'annee_scolaire' => ['required', 'string', 'max:20'],
            'prefixe_matricule' => ['nullable', 'string', 'max:10'],
            'bulletin_signature_nom' => ['nullable', 'string', 'max:120'],
            'bulletin_signature_titre' => ['nullable', 'string', 'max:120'],
            'bulletin_mention' => ['nullable', 'string', 'max:300'],
            'bulletin_afficher_rang' => ['nullable', 'boolean'],
            'bulletin_couleur' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'couleur_principale' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            // Position de l'école : sert au contrôle du pointage des enseignants (distance maximale autorisée).
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'rayon_pointage' => ['nullable', 'integer', 'between:20,1000'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }
}
