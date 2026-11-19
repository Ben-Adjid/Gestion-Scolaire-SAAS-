<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InscriptionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return \App\Services\CompteParentService::regles() + [
            'etudiant_id' => ['required_if:type,ancien', 'nullable', 'exists:etudiants,id'],
            'nom' => ['required_without:etudiant_id', 'nullable', 'string', 'max:255'],
            'prenom' => ['required_without:etudiant_id', 'nullable', 'string', 'max:255'],
            'date_naissance' => ['nullable', 'date', 'before:today'],
            'genre' => ['nullable', Rule::in(['M', 'F'])],
            'classe_id' => ['required', 'exists:classes,id'],
            'annee_scolaire' => ['required', 'string', 'max:20'],
            'date_inscription' => ['required', 'date'],
            'statut' => ['required', Rule::in(['active', 'terminee', 'annulee'])],
            'type' => ['required', Rule::in(['nouveau', 'ancien'])],
        ];
    }

    public function messages(): array
    {
        return [
            'date_naissance.before' => 'La date de naissance doit être antérieure à aujourd’hui.',
            'date_naissance.date' => 'La date de naissance n’est pas valide. Utilisez le format jour/mois/année.',
        ];
    }
}
