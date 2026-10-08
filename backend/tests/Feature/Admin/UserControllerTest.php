<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $targetUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin', 'status' => 'actif']);
        // Ensure the admin user has an admin profile and it's active
        if (! $this->adminUser->admin()->exists()) { // Check if the relationship exists
            Admin::factory()->active()->create(['user_id' => $this->adminUser->id]);
        } else {
            $this->adminUser->admin->update(['admin_status' => 1]);
        }
        $this->adminUser->refresh(); // Refresh to load the admin relationship

        Sanctum::actingAs($this->adminUser, ['role:admin']);

        $this->targetUser = User::factory()->create(['role' => 'patient']);
        Patient::factory()->create(['user_id' => $this->targetUser->id]);
    }

    public function test_admin_can_list_users()
    {
        User::factory(5)->create();
        $response = $this->getJson('/api/admin/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'current_page',
                'data' => [
                    '*' => ['id', 'nom', 'prenom', 'email', 'role', 'status'],
                ],
                'total',
            ]);
        // Adjust count assertion if necessary, as it depends on how many users are created by default
        // For instance, if User::factory(5) creates 5 users + targetUser + adminUser = 7 total
        // The controller might filter out the acting admin, so it could be 6.
        // ->assertJsonCount(User::where('id', '!=', $this->adminUser->id)->count(), 'data');
    }

    public function test_admin_can_filter_users_by_role()
    {
        User::factory(2)->create(['role' => 'medecin']);
        $response = $this->getJson('/api/admin/users?role=medecin');

        $response->assertStatus(200);
        foreach ($response->json('data') as $user) {
            $this->assertEquals('medecin', $user['role']);
        }
    }

    public function test_admin_can_search_users()
    {
        $searchableUser = User::factory()->create(['nom' => 'SearchMe', 'role' => 'patient']);
        $response = $this->getJson('/api/admin/users?search=SearchMe');

        $response->assertStatus(200)
            ->assertJsonFragment(['nom' => 'SearchMe']);
    }

    public function test_admin_can_store_new_admin_user()
    {
        $adminData = [
            'nom' => 'NewAdmin',
            'prenom' => 'Test',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'current_password' => 'password',
            'telephone' => '0600000000',
        ];

        $response = $this->postJson('/api/admin/users/admin', $adminData);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Admin user created successfully')
            ->assertJsonPath('user.email', 'newadmin@example.com')
            ->assertJsonPath('user.role', 'admin')
            ->assertJsonPath('admin.admin_status', 1); // Assuming admin_status is a boolean or tinyInt

        $this->assertDatabaseHas('users', ['email' => 'newadmin@example.com', 'role' => 'admin']);
        $newUser = User::where('email', 'newadmin@example.com')->first();
        $this->assertDatabaseHas('admins', ['user_id' => $newUser->id, 'admin_status' => 1]); // Check for 1 if it's active
        $this->assertDatabaseHas('audit_logs', ['action' => 'created_admin', 'target_id' => $newUser->id]);
    }

    public function test_admin_can_update_user_status()
    {
        $response = $this->putJson('/api/admin/users/'.$this->targetUser->id.'/status', $this->statusDecision($this->targetUser, ['status' => 'inactif']));

        $response->assertStatus(200)
            ->assertJsonPath('message', 'User status updated successfully')
            ->assertJsonPath('user.status', 'inactif');
        $this->assertDatabaseHas('users', ['id' => $this->targetUser->id, 'status' => 'inactif']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'updated_user_status', 'target_id' => $this->targetUser->id]);
    }

    public function test_admin_can_update_admin_user_status()
    {
        $otherAdminUser = User::factory()->create(['role' => 'admin']);
        $otherAdminProfile = Admin::factory()->create(['user_id' => $otherAdminUser->id, 'admin_status' => 1]);

        $response = $this->putJson("/api/admin/admins/{$otherAdminProfile->id}/status", $this->statusDecision($otherAdminUser, ['admin_status' => 0]));
        $response->assertStatus(200)
            ->assertJsonPath('message', 'Admin status updated successfully')
            ->assertJsonPath('admin.admin_status', 0);
        $this->assertDatabaseHas('admins', ['id' => $otherAdminProfile->id, 'admin_status' => 0]);
    }

    public function test_admin_can_show_user_details()
    {
        $doctorUser = User::factory()->create(['role' => 'medecin']);
        Doctor::factory()->create(['user_id' => $doctorUser->id]);

        $response = $this->getJson('/api/admin/users/'.$doctorUser->id);
        $response->assertStatus(200)
            ->assertJsonPath('user.id', $doctorUser->id)
            ->assertJsonPath('doctor.user_id', $doctorUser->id);
    }

    public function test_admin_can_reset_user_password()
    {
        $newPassword = 'newPassword123';
        $response = $this->putJson('/api/admin/users/'.$this->targetUser->id.'/password', [
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
            'current_password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Password reset successfully');

        $this->targetUser->refresh();
        $this->assertTrue(Hash::check($newPassword, $this->targetUser->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'reset_user_password', 'target_id' => $this->targetUser->id]);
    }

    public function test_admin_cannot_permanently_delete_user()
    {
        $userToDelete = User::factory()->create();
        $response = $this->deleteJson('/api/admin/users/'.$userToDelete->id);

        $response->assertStatus(409)->assertJsonPath('code', 'account_deletion_unavailable');
        $this->assertDatabaseHas('users', ['id' => $userToDelete->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'deleted_user', 'target_id' => $userToDelete->id]);
    }

    public function test_non_active_admin_cannot_perform_actions()
    {
        // Ensure the admin relationship exists before trying to update it
        if (! $this->adminUser->admin) {
            Admin::factory()->active()->create(['user_id' => $this->adminUser->id]);
            $this->adminUser->refresh();
        }
        $this->adminUser->admin->update(['admin_status' => 0]); // Deactivate the admin

        // Re-authenticate or ensure the current actingAs user reflects this change.
        // For API tests with Sanctum, the token is what matters.
        // The ActiveUser middleware should pick up the change from the database.
        // If ActiveUser middleware relies on the initially authenticated user state,
        // you might need to re-authenticate or mock how it fetches the admin status.
        // For this test, we assume the ActiveUser middleware correctly checks the DB.

        $response = $this->getJson('/api/admin/users');
        $response->assertStatus(403)->assertJson(['message' => 'Administrator access is disabled.']);
    }
}
