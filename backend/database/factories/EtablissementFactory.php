<?php

namespace Database\Factories;

use App\Models\Etablissement;
use Illuminate\Database\Eloquent\Factories\Factory;

class EtablissementFactory extends Factory
{
    protected $model = Etablissement::class;

    public function definition()
    {
        return [
            'nom' => $this->faker->unique()->company(),
            'statut_abonnement' => 'actif',
            'abonnement_fin' => null,
        ];
    }
}
