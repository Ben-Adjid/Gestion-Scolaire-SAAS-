<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PointageRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'emploi_du_temps_id' => ['required', 'exists:emploi_du_temps,id'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
