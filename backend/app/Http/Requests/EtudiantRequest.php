<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EtudiantRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return $this->isMethod('post')
            ? auth()->user()->can('create', \App\Models\Etudiant::class)
            : auth()->user()->can('update', $this->route('etudiant'));
    }

    public function rules(): array
    {
        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');
        
        return [
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'date_naissance' => 'required|date|before:today',
            'genre' => 'required|in:M,F',
            'email' => 'nullable|email|max:255' . ($isUpdate ? '' : '|unique:etudiants'),
            'telephone' => 'nullable|string|max:20',
            'adresse' => 'nullable|string|max:255',
            'classe_id' => 'required|exists:classes,id',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom est requis.',
            'prenom.required' => 'Le prénom est requis.',
            'date_naissance.required' => 'La date de naissance est requise.',
            'classe_id.required' => 'La classe est requise.',
            'classe_id.exists' => 'La classe sélectionnée n\'existe pas.',
        ];
    }
}
