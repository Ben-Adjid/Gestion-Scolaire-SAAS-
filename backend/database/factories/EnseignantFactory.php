<?php

namespace Database\Factories;

use App\Models\Enseignant;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnseignantFactory extends Factory
{
    protected $model = Enseignant::class;

    public function definition()
    {
        return [
            'nom' => $this->faker->lastName(),
            'prenom' => $this->faker->firstName(),
            'email' => $this->faker->unique()->safeEmail(),
            'telephone' => $this->faker->optional(0.7)->phoneNumber(),
            'specialite' => $this->faker->randomElement([
                'Mathématiques', 'Physique-Chimie', 'Français', 'Anglais',
                'Histoire-Géographie', 'SVT', 'EPS', 'Informatique', 'Philosophie',
            ]),
        ];
    }
}
