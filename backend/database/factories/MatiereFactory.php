<?php

namespace Database\Factories;

use App\Models\Matiere;
use Illuminate\Database\Eloquent\Factories\Factory;

class MatiereFactory extends Factory
{
    protected $model = Matiere::class;

    public function definition()
    {
        return [
            'nom' => $this->faker->unique()->randomElement([
                'Mathématiques', 'Français', 'Anglais', 'Histoire-Géographie',
                'SVT', 'Physique-Chimie', 'EPS', 'Philosophie',
                'Espagnol', 'Informatique',
            ]),
            'code' => strtoupper($this->faker->unique()->lexify('???')).$this->faker->unique()->numerify('##'),
            'coefficient' => $this->faker->randomElement([1, 1, 2, 2, 3, 4]),
            'couleur' => $this->faker->hexColor(),
        ];
    }
}
