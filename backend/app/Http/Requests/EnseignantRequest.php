<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnseignantRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('enseignants', 'email')->ignore($this->route('enseignant')),
            ],
            'telephone' => ['nullable', 'string', 'max:30'],
            'specialite' => ['nullable', 'string', 'max:255'],
        ];
    }
}
