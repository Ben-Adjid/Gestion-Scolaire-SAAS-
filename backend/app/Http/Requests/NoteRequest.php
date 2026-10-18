<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['enseignant', 'proviseur'], true);
    }

    public function rules(): array
    {
        return [
            'etudiant_id' => 'required|exists:etudiants,id',
            'matiere_id' => 'required|exists:matieres,id',
            'valeur' => 'required|numeric|min:0|max:20',
            'type' => 'nullable|in:devoir,composition,examen',
            'appreciation' => 'nullable|string|max:500',
            'trimestre' => 'required|in:1,2,3',
        ];
    }

    public function messages(): array
    {
        return [
            'valeur.required' => 'La note est requise.',
            'valeur.numeric' => 'La note doit être un nombre.',
            'valeur.min' => 'La note doit être >= 0.',
            'valeur.max' => 'La note doit être <= 20.',
            'trimestre.required' => 'Le trimestre est requis.',
        ];
    }
}
