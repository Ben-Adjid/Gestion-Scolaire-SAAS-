<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'role' => [
                'required',
                // Un proviseur crée le personnel (pas de compte de direction ni de plateforme) ; l'admin attribue tout sauf la plateforme.
                Rule::in($this->user()?->isSuperAdmin()
                    ? User::ROLES
                    : ($this->user()?->role === 'proviseur'
                        ? array_diff(User::ROLES, ['super_admin', 'admin', 'proviseur'])
                        : array_diff(User::ROLES, ['super_admin']))),
            ],
            'enseignant_id' => ['nullable', 'exists:enseignants,id'],
            // Direction et vie scolaire : « Général », un cycle ou un bâtiment.
            'affectation' => ['nullable', 'string', 'max:100'],
            // Enseignant : la fiche (matières, code de pointage) est créée avec le compte si aucune n'est choisie.
            'matieres' => ['nullable', 'array'],
            'matieres.*' => ['integer', 'exists:matieres,id'],
            'specialite' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'code')],
            'password' => [
                $this->isMethod('post') ? 'required' : 'nullable',
                'string',
                'min:6',
            ],
        ];
    }
}
