<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DossierInscriptionRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'acte_naissance' => ['nullable', 'boolean'],
            'acte_naissance_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'bulletin' => ['nullable', 'boolean'],
            'bulletin_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
            'photo' => ['nullable', 'boolean'],
            'photo_file' => ['nullable', 'image', 'max:2048'],
            'observations' => ['nullable', 'string', 'max:500'],
        ];
    }
}
