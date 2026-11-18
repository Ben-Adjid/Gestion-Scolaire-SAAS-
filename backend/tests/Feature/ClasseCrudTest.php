<?php

namespace Tests\Feature;

use App\Models\Classe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClasseCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_classe_peut_etre_modifiee_et_supprimee(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $classe = Classe::factory()->create(['nom' => 'Ancien nom']);

        $this->putJson("/api/classes/{$classe->id}", [
            'nom' => 'Nouveau nom',
            'niveau' => '6ème',
            'effectif_max' => 35,
            'cycle' => 'college',
        ])->assertOk();
        $this->assertEquals('Nouveau nom', $classe->fresh()->nom);

        $this->deleteJson("/api/classes/{$classe->id}")->assertOk();
        $this->assertNull(Classe::find($classe->id));
    }
}
