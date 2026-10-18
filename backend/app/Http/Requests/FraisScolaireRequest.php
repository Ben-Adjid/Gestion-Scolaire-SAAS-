<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FraisScolaireRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'etudiant_id' => [$this->isMethod('post') ? 'required' : 'nullable', 'exists:etudiants,id'],
            'annee_scolaire' => ['required', 'string', 'max:20'],
            'montant_total' => ['required', 'numeric', 'min:0'],
            'montant_paye' => ['nullable', 'numeric', 'min:0'],
            'observations' => ['nullable', 'string', 'max:500'],
        ];
    }
}
