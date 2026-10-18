<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegleRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'note_max' => ['required', 'numeric', 'between:1,100'],
            'seuil_admission' => ['required', 'numeric', 'between:0,100'],
            'mention_felicitations' => ['required', 'numeric', 'between:0,100'],
            'mention_honneur' => ['required', 'numeric', 'between:0,100'],
            'mention_encouragements' => ['required', 'numeric', 'between:0,100'],
            'nb_tableau_honneur' => ['required', 'integer', 'min:1', 'max:50'],
        ];
    }
}
