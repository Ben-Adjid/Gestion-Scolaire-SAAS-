<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClasseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->can('create', \App\Models\Classe::class);
    }

    /** Le cycle se déduit du niveau quand le formulaire ne l'envoie pas. */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('cycle')) && filled($this->input('niveau'))) {
            $this->merge(['cycle' => \App\Models\Classe::cyclePourNiveau($this->input('niveau'))]);
        }
        if ($this->has('batiment')) {
            $this->merge(['batiment' => filled($this->input('batiment')) ? trim((string) $this->input('batiment')) : null]);
        }
    }

    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:100',
            'niveau' => 'required|string|max:50',
            'description' => 'nullable|string|max:500',
            'effectif_max' => 'nullable|integer|min:5|max:150',
            'cycle' => 'required|in:college,lycee',
            'batiment' => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom de la classe est requis.',
            'niveau.required' => 'Le niveau est requis.',
        ];
    }
}
