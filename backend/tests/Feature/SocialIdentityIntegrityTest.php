<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SocialIdentityIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_10_07_000006_unique_social_identities.php');
    }

    public function test_same_id_is_scoped_to_provider_and_duplicate_mapping_is_rejected(): void
    {
        User::factory()->create(['provider' => 'google', 'provider_id' => 'fixture-123']);
        User::factory()->create(['provider' => 'facebook', 'provider_id' => 'fixture-123']);
        $this->assertDatabaseCount('users', 2);
        try {
            User::factory()->create(['provider' => 'google', 'provider_id' => 'fixture-123']);
            $this->fail('Expected a duplicate identity constraint failure');
        } catch (QueryException $exception) {
            $this->assertSame('23000', (string) $exception->getCode());
        }
        $this->assertDatabaseCount('users', 2);
    }

    public function test_multiple_password_accounts_are_allowed_without_provider_mappings(): void
    {
        User::factory()->count(3)->create(['provider' => null, 'provider_id' => null]);
        $this->assertDatabaseCount('users', 3);
        $this->assertTrue(Schema::hasIndex('users', 'users_provider_identity_unique'));
    }

    public function test_duplicate_preflight_stops_before_ddl_without_merging_or_deleting_users(): void
    {
        $migration = $this->migration();
        $migration->down();
        User::factory()->count(2)->create(['provider' => 'google', 'provider_id' => 'fixture-duplicate']);
        try {
            $migration->up();
            $this->fail('Expected migration preflight failure');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Duplicate social identity', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 2);
        $this->assertFalse(Schema::hasIndex('users', 'users_provider_identity_unique'));
    }

    public function test_partial_or_blank_preflight_stops_without_changing_data(): void
    {
        $migration = $this->migration();
        $migration->down();
        User::factory()->create(['provider' => 'google', 'provider_id' => null]);
        User::factory()->create(['provider' => null, 'provider_id' => 'orphan-fixture']);
        User::factory()->create(['provider' => 'facebook', 'provider_id' => '   ']);
        try {
            $migration->up();
            $this->fail('Expected migration preflight failure');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Incomplete or blank', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 3);
        $this->assertFalse(Schema::hasIndex('users', 'users_provider_identity_unique'));
        $this->assertDatabaseHas('users', ['provider' => 'facebook', 'provider_id' => '   ']);
    }

    public function test_valid_historical_data_survives_index_creation_and_rollback(): void
    {
        $migration = $this->migration();
        $migration->down();
        $user = User::factory()->create(['provider' => 'google', 'provider_id' => 'fixture-history']);
        User::factory()->create(['provider' => null, 'provider_id' => null]);
        $migration->up();
        $this->assertTrue(Schema::hasIndex('users', 'users_provider_identity_unique'));
        $this->assertSame('fixture-history', $user->fresh()->provider_id);
        $this->assertDatabaseCount('users', 2);
        $migration->down();
        $this->assertFalse(Schema::hasIndex('users', 'users_provider_identity_unique'));
        $this->assertSame('fixture-history', $user->fresh()->provider_id);
    }

    public function test_blank_mapping_alone_stops_the_preflight(): void
    {
        $migration = $this->migration();
        $migration->down();
        User::factory()->create(['provider' => 'google', 'provider_id' => '   ']);
        try {
            $migration->up();
            $this->fail('Expected blank mapping preflight failure');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Incomplete or blank', $exception->getMessage());
        }
        $this->assertDatabaseCount('users', 1);
        $this->assertFalse(Schema::hasIndex('users', 'users_provider_identity_unique'));
    }
}
