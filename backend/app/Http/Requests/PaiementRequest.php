<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaiementRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'montant' => ['required', 'numeric', 'gt:0'],
            'date_paiement' => ['required', 'date'],
            'mode' => ['nullable', Rule::in(['especes', 'virement', 'cheque', 'mobile_money'])],
            'reference' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:500'],
        ];
    }
}
