<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProfilePhotoSafetyTest extends TestCase
{
    use DatabaseMigrations;

    public static function roles(): array
    {
        return [['patient'], ['doctor']];
    }

    private function context(string $role): array
    {
        Storage::fake('public');
        $user = User::factory()->create(['role' => $role === 'doctor' ? 'medecin' : 'patient', 'status' => 'actif', 'email_verified_at' => now(), 'photo' => 'users/original.jpg']);
        Storage::disk('public')->put($user->photo, 'Original photo');
        if ($role === 'doctor') {
            Doctor::factory()->create(['user_id' => $user->id]);
        } else {
            Patient::factory()->create(['user_id' => $user->id]);
        }
        Sanctum::actingAs($user);

        return [$user, '/api/'.$role.'/profile/photo', Storage::disk('public')];
    }

    #[DataProvider('roles')]
    public function test_replacement_persists_new_photo_before_removing_old_file(string $role): void
    {
        [$user, $url, $disk] = $this->context($role);
        Event::listen('eloquent.updating: '.User::class, function (User $saving) use ($disk) {
            $this->assertTrue($disk->exists($saving->getOriginal('photo')));
            $this->assertTrue($disk->exists($saving->photo));
        });
        $response = $this->postJson($url, ['photo' => UploadedFile::fake()->image('new.png')])
            ->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonMissingPath('user.auth_version');
        $path = $response->json('path');
        $this->assertSame($path, $user->fresh()->photo);
        $this->assertSame($path, $response->json('user.photo'));
        $this->assertTrue($disk->exists($path));
        $this->assertFalse($disk->exists('users/original.jpg'));
    }

    #[DataProvider('roles')]
    public function test_invalid_and_oversized_photos_return_validation_errors_without_modifying_existing_photo(string $role): void
    {
        [$user, $url, $disk] = $this->context($role);
        foreach ([UploadedFile::fake()->create('document.pdf', 20, 'application/pdf'), UploadedFile::fake()->image('large.jpg')->size(5121)] as $file) {
            $this->postJson($url, ['photo' => $file])->assertUnprocessable()->assertJsonValidationErrors('photo');
        }
        $this->assertSame('users/original.jpg', $user->fresh()->photo);
        $this->assertSame(['users/original.jpg'], $disk->allFiles());
    }

    #[DataProvider('roles')]
    public function test_storage_write_failure_preserves_old_photo_and_returns_safe_error(string $role): void
    {
        [$user, $url, $disk] = $this->context($role);
        $unavailable = Mockery::mock(FilesystemAdapter::class);
        $unavailable->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($unavailable);
        $this->postJson($url, ['photo' => UploadedFile::fake()->image('new.jpg')])->assertStatus(503)
            ->assertJsonPath('message', 'Unable to save your photo. Please retry.');
        $this->assertSame('users/original.jpg', $user->fresh()->photo);
        $this->assertTrue($disk->exists('users/original.jpg'));
    }

    #[DataProvider('roles')]
    public function test_database_failure_cleans_up_new_file_but_preserves_old_photo(string $role): void
    {
        [$user, $url, $disk] = $this->context($role);
        Event::listen('eloquent.updating: '.User::class, fn () => throw new \RuntimeException('Sensitive storage/database diagnostic'));
        $response = $this->postJson($url, ['photo' => UploadedFile::fake()->image('new.jpg')])->assertStatus(503)
            ->assertJsonPath('message', 'Unable to save your photo. Please retry.');
        $this->assertStringNotContainsString('Sensitive storage/database diagnostic', $response->getContent());
        $this->assertSame('users/original.jpg', $user->fresh()->photo);
        $this->assertSame(['users/original.jpg'], $disk->allFiles());
    }

    public function test_old_file_cleanup_failure_does_not_undo_committed_photo(): void
    {
        [$user, $url, $disk] = $this->context('patient');
        $cleanupFailure = Mockery::mock(FilesystemAdapter::class);
        $cleanupFailure->shouldReceive('putFileAs')->once()->andReturnUsing(fn (...$args) => $disk->putFileAs(...$args));
        $cleanupFailure->shouldReceive('delete')->with('users/original.jpg')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($cleanupFailure);
        $response = $this->postJson($url, ['photo' => UploadedFile::fake()->image('new.jpg')])->assertOk();
        $this->assertSame($response->json('path'), $user->fresh()->photo);
        $this->assertTrue($disk->exists($user->fresh()->photo));
        $this->assertTrue($disk->exists('users/original.jpg'));
    }

    public function test_legacy_photo_path_outside_managed_folders_is_not_deleted(): void
    {
        [$user, $url, $disk] = $this->context('doctor');
        $disk->put('doctor-documents/credential.pdf', 'Credential');
        $user->update(['photo' => 'doctor-documents/credential.pdf']);
        $this->postJson($url, ['photo' => UploadedFile::fake()->image('new.jpg')])->assertOk();
        $this->assertTrue($disk->exists('doctor-documents/credential.pdf'));
    }
}
