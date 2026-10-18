<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DossierExamenRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'examen' => ['required', Rule::in(['brevet', 'bac'])],
            'acte_naissance' => ['nullable', 'boolean'],
            'acte_naissance_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'photos_identite' => ['nullable', 'boolean'],
            'photos_identite_file' => ['nullable', 'image', 'max:2048'],
            'fiche_renseignement' => ['nullable', 'boolean'],
            'fiche_renseignement_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'paiement' => ['nullable', 'boolean'],
            'observations' => ['nullable', 'string', 'max:500'],
        ];
    }
}
