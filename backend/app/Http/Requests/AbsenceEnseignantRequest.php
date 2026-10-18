<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AbsenceEnseignantRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(['absence', 'retard'])],
            'motif' => ['nullable', 'string', 'max:255'],
            'justifie' => ['nullable', 'boolean'],
        ];
    }
}
