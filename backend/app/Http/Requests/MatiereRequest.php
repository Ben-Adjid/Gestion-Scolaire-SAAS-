<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MatiereRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'coefficient' => ['required', 'integer', 'min:1', 'max:20'],
            'couleur' => ['nullable', 'string', 'max:20'],
        ];
    }
}
