<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConvocationRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'etudiant_id' => ['required', 'exists:etudiants,id'],
            'date' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:255'],
        ];
    }
}
