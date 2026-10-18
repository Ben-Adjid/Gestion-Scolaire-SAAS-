<?php

namespace Database\Factories;

use App\Models\Etudiant;
use App\Models\Matiere;
use App\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition()
    {
        return [
            'etudiant_id' => Etudiant::factory(),
            'matiere_id' => Matiere::factory(),
            'trimestre' => $this->faker->randomElement([1, 2, 3]),
            'type' => $this->faker->randomElement(['devoir', 'composition', 'examen']),
            'valeur' => $this->faker->randomFloat(2, 6, 20),
            'appreciation' => $this->faker->optional(0.4)->sentence(4),
        ];
    }
}
