<?php

namespace Database\Factories;

use App\Models\Etudiant;
use Illuminate\Database\Eloquent\Factories\Factory;

class EtudiantFactory extends Factory
{
    protected $model = Etudiant::class;

    public function definition()
    {
        return [
            'nom' => $this->faker->lastName(),
            'prenom' => $this->faker->firstName(),
            'date_naissance' => $this->faker->dateTimeBetween('-20 years', '-10 years')->format('Y-m-d'),
            'genre' => $this->faker->randomElement(['M', 'F']),
            'email' => $this->faker->unique()->safeEmail(),
            'telephone' => $this->faker->phoneNumber(),
            'adresse' => $this->faker->address(),
            'classe_id' => \App\Models\Classe::inRandomOrder()->first()->id ?? \App\Models\Classe::factory(),
            'etablissement_id' => 1,
        ];
    }
}
