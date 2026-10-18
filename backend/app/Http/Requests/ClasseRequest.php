<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('create', \App\Models\Classe::class);
    }

    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:100',
            'niveau' => 'required|string',
            'effectif_max' => 'required|integer|min:10|max:40',
            'cycle' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de la classe est requis.',
            'niveau.required' => 'Le niveau est requis.',
            'effectif_max.required' => 'L\'effectif maximum est requis.',
        ];
    }
}
