<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppelRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'classe_id' => ['required', 'exists:classes,id'],
            'date' => ['required', 'date'],
            'valider' => ['nullable', 'boolean'],
            'presences' => ['required', 'array', 'min:1'],
            'presences.*.etudiant_id' => ['required', 'exists:etudiants,id'],
            'presences.*.statut' => ['required', Rule::in(['present', 'absence', 'retard'])],
        ];
    }
}
