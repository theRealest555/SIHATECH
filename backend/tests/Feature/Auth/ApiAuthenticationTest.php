<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_correct_credentials_and_receives_token(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'role' => 'patient',
            'email_verified_at' => now(),
            'status' => 'actif',
        ]);

        $response = $this->postJson('/api/login', [ // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'nom', 'prenom', 'email', 'role', 'status', 'email_verified_at'],
                'role',
                'token',
            ])
            ->assertJson([
                'user' => [
                    'email' => $user->email,
                    'role' => 'patient',
                    'status' => 'actif',
                    'email_verified_at' => $user->email_verified_at->toISOString(),
                    'nom' => $user->nom,
                    'prenom' => $user->prenom,
                ],
                'role' => 'patient',
            ]);

        $this->assertAuthenticatedAs($user, 'sanctum');
    }

    public function test_user_cannot_login_with_incorrect_password(): void
    {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
        ]);

        $response = $this->postJson('/api/login', [ // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422) // Laravel returns 422 for failed authentication attempts
            ->assertJsonValidationErrors(['email']); // Breeze default returns error on 'email' field for general auth failure
        $this->assertGuest();
    }

    public function test_user_cannot_login_with_non_existent_email(): void
    {
        $response = $this->postJson('/api/login', [ // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/login', []); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create(['role' => 'patient']);
        Sanctum::actingAs($user, ['role:patient']); // Simulate login with specific ability

        $response = $this->postJson('/api/logout'); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);
        // To fully test token invalidation, you might need to check if the token can be used again.
        // However, Sanctum's token invalidation is usually handled by its internals.
        // A simple check is that subsequent requests with the same token would fail,
        // but that's harder to test directly here without storing the token.
    }

    public function test_unauthenticated_user_cannot_logout(): void
    {
        $response = $this->postJson('/api/logout'); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
        $response->assertStatus(401); // Expect Unauthenticated
    }

    public function test_admin_can_login_via_admin_route(): void
    {
        $adminUser = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => Hash::make('adminPass123'),
            'role' => 'admin',
            'email_verified_at' => now(),
            'status' => 'actif',
        ]);
        // Ensure the Admin model record exists if your AdminAuthController checks it
        Admin::factory()->create(['user_id' => $adminUser->id, 'admin_status' => 1]);

        $response = $this->postJson('/api/admin/login', [ // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
            'email' => 'admin@example.com',
            'password' => 'adminPass123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'email', 'role'],
                'token',
            ])
            ->assertJsonPath('user.role', 'admin');

        // $this->assertAuthenticatedAs($adminUser, 'sanctum');
    }

    public function test_non_admin_cannot_login_via_admin_route(): void
    {
        User::factory()->create([
            'email' => 'patient@example.com',
            'password' => Hash::make('password123'),
            'role' => 'patient',
        ]);

        $response = $this->postJson('/api/admin/login', [ // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]
            'email' => 'patient@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(422); // Or 403 if your AdminAuthController explicitly checks role before attempting auth
        $this->assertGuest();
    }

    public function test_admin_can_logout_via_admin_route(): void
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        // \App\Models\Admin::factory()->create(['user_id' => $adminUser->id]);
        Sanctum::actingAs($adminUser, ['role:admin']);

        $response = $this->postJson('/api/admin/logout'); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/api.php]

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);
    }

    public function test_csrf_cookie_can_be_obtained(): void
    {
        $response = $this->get('/sanctum/csrf-cookie'); // [cite: therealest555/sihatech2/SIHATECH2-bfec2d9e1e08e8149fc892e74235c175d08bed7c/backend/routes/web.php]
        $response->assertStatus(204)
            ->assertCookie('XSRF-TOKEN');
    }
}
