<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MessageRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'type' => ['required', Rule::in(['annonce', 'devoir', 'examen', 'sujet'])],
            'cible' => ['required', Rule::in(['tous', 'enseignants', 'administration'])],
            'titre' => ['required', 'string', 'max:255'],
            'contenu' => ['required', 'string', 'max:5000'],
            'fichier' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:5120'],
        ];
    }
}
