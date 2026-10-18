<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NoteBulkRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'matiere_id' => ['required', 'exists:matieres,id'],
            'trimestre' => ['required', 'integer', 'between:1,3'],
            'type' => ['required', 'string', 'max:50'],
            'valider' => ['nullable', 'boolean'],
            'notes' => ['required', 'array', 'min:1'],
            'notes.*.etudiant_id' => ['required', 'exists:etudiants,id'],
            'notes.*.valeur' => ['required', 'numeric', 'between:0,20'],
            'notes.*.appreciation' => ['nullable', 'string', 'max:500'],
        ];
    }
}
