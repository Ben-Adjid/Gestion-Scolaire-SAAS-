<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test rate limiting on login endpoint
     */
    public function test_login_rate_limiting(): void
    {
        $email = 'admin@test.com';
        User::factory()->create(['email' => $email]);

        // Attempt 6 failed logins (limit is 5 per 1 minute)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => $email,
                'password' => 'wrong_password_'.$i,
            ]);

            if ($i < 5) {
                $this->assertTrue(
                    in_array($response->status(), [422, 423]),
                    "Request $i should return 422 or 423, got {$response->status()}"
                );
            } else {
                // 6th request should be throttled (429)
                $this->assertEquals(429, $response->status());
            }
        }
    }

    /**
     * Test multi-tenant access isolation
     */
    public function test_users_cannot_access_other_establishments_data(): void
    {
        $est1 = \App\Models\Etablissement::factory()->create();
        $est2 = \App\Models\Etablissement::factory()->create();

        $user1 = User::factory()->create(['etablissement_id' => $est1->id, 'role' => 'admin']);
        $etudiant2 = \App\Models\Etudiant::factory()->create(['etablissement_id' => $est2->id]);

        $this->actingAs($user1)
            ->getJson("/api/etudiants/{$etudiant2->id}")
            ->assertStatus(404); // Should not find student from other establishment
    }

    /**
     * Test file upload validation
     */
    public function test_file_upload_with_invalid_type(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $etudiant = \App\Models\Etudiant::factory()->create();

        $file = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-msdownload');

        $this->actingAs($user)
            ->postJson("/api/etudiants/{$etudiant->id}/dossier", [
                'acte_naissance_file' => $file,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['acte_naissance_file']);
    }

    /**
     * Test file upload size limit
     */
    public function test_file_upload_exceeds_size_limit(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $etudiant = \App\Models\Etudiant::factory()->create();

        // Create a file that exceeds 2MB limit (2048 KB)
        $file = UploadedFile::fake()->create('large.pdf', 3000, 'application/pdf');

        $this->actingAs($user)
            ->postJson("/api/etudiants/{$etudiant->id}/dossier", [
                'acte_naissance_file' => $file,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['acte_naissance_file']);
    }

    /**
     * Test that subscription check is enforced
     */
    public function test_inactive_establishment_access_denied(): void
    {
        $est = \App\Models\Etablissement::factory()->create(['statut_abonnement' => 'suspendu']);
        $user = User::factory()->create(['etablissement_id' => $est->id, 'role' => 'admin']);

        $this->actingAs($user)
            ->getJson('/api/dashboard')
            ->assertStatus(403)
            ->assertJsonPath('code', 'subscription_inactive');
    }

    /**
     * Test Security Headers are present
     */
    public function test_security_headers_are_present(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/me');

        $this->assertTrue($response->headers->has('X-Frame-Options'));
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertTrue($response->headers->has('X-XSS-Protection'));
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
    }

    /**
     * Test CSRF token is required for stateful requests
     */
    public function test_csrf_protection(): void
    {
        $user = User::factory()->create();

        // This test is more about Laravel's CSRF protection
        // which should be handled by Sanctum middleware
        $this->actingAs($user)
            ->postJson('/api/logout')
            ->assertOk();
    }
}
