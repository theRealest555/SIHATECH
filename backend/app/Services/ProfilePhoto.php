<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProfilePhoto
{
    public function replace(int $userId, UploadedFile $photo, string $folder): User
    {
        $path = null;
        try {
            $path = $photo->store($folder, 'public');
            if (! $path) {
                throw new \RuntimeException('Profile photo storage failed.');
            }

            return DB::transaction(function () use ($userId, $path) {
                $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
                $oldPath = $user->photo;
                $user->update(['photo' => $path]);
                DB::afterCommit(fn () => $this->deleteManagedPhoto($oldPath));

                return $user;
            }, 3);
        } catch (\Throwable $exception) {
            if ($path) {
                $this->deleteManagedPhoto($path);
            }
            report($exception);
            throw new HttpException(503, 'Unable to save your photo. Please retry.');
        }
    }

    private function deleteManagedPhoto(?string $path): void
    {
        // Never treat a legacy URL, absolute path or another storage area as ours.
        if (! $path || ! preg_match('#^(users|doctors)/[^/\\\\]+$#', $path) || str_contains($path, '..')) {
            return;
        }
        try {
            if (! Storage::disk('public')->delete($path)) {
                report(new \RuntimeException('Unable to remove an unused profile photo.'));
            }
        } catch (\Throwable $exception) {
            // A cleanup failure must not invalidate the committed replacement.
            report($exception);
        }
    }
}
