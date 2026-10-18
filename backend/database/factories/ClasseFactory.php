<?php

namespace Database\Factories;

use App\Models\Classe;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClasseFactory extends Factory
{
    protected $model = Classe::class;

    public function definition()
    {
        $niveaux = ['6ème', '5ème', '4ème', '3ème', 'Seconde', 'Première', 'Terminale'];
        $cycles = ['collège', 'lycée'];

        return [
            'nom' => 'Classe ' . $this->faker->randomElement($niveaux) . ' ' . $this->faker->randomLetter(),
            'niveau' => $this->faker->randomElement($niveaux),
            'effectif_max' => $this->faker->numberBetween(30, 40),
            'cycle' => $this->faker->randomElement($cycles),
            'etablissement_id' => 1,
        ];
    }
}
