<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['enseignant', 'cpe', 'admin', 'censeur']);
    }

    public function rules(): array
    {
        return [
            'etudiant_id' => 'required|exists:etudiants,id',
            'date' => 'required|date',
            'type' => 'nullable|string|max:20',
            'justifie' => 'nullable|boolean',
            'motif' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'etudiant_id.required' => 'L\'élève est requis.',
            'date.required' => 'La date est requise.',
        ];
    }
}
