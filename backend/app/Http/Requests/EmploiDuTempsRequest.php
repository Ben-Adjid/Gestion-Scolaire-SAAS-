<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmploiDuTempsRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'classe_id' => ['required', 'exists:classes,id'],
            'matiere_id' => ['required', 'exists:matieres,id'],
            'enseignant_id' => ['nullable', 'exists:enseignants,id'],
            'jour' => ['required', 'integer', 'between:1,6'],
            'creneau_id' => ['required', 'exists:creneaux,id'],
            'salle' => ['nullable', 'string', 'max:50'],
        ];
    }
}
