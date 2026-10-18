<?php

namespace Database\Factories;

use App\Models\Classe;
use App\Models\Etudiant;
use App\Models\Inscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class InscriptionFactory extends Factory
{
    protected $model = Inscription::class;

    public function definition()
    {
        return [
            'etudiant_id' => Etudiant::factory(),
            'classe_id' => Classe::factory(),
            'annee_scolaire' => '2025-2026',
            'date_inscription' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'statut' => $this->faker->randomElement(['active', 'active', 'active', 'terminee']),
            'type' => $this->faker->randomElement(['nouveau', 'nouveau', 'ancien']),
        ];
    }
}
