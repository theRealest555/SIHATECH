<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\DocumentFileCleanup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CleanupDocumentFiles extends Command
{
    protected $signature = 'documents:cleanup-files {--limit=100 : Maximum queued files to attempt}';

    protected $description = 'Remove unreferenced private credential files from the durable cleanup queue';

    public function handle(): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('Limit must be between 1 and 1000.');

            return self::INVALID;
        }
        $ids = DocumentFileCleanup::orderBy('last_attempt_at')->orderBy('id')->limit($limit)->pluck('id');
        $failed = 0;
        foreach ($ids as $id) {
            $ok = DB::transaction(function () use ($id) {
                $item = DocumentFileCleanup::whereKey($id)->lockForUpdate()->first();
                if (! $item) {
                    return true;
                }
                $item->attempts++;
                $item->last_attempt_at = now();
                $path = $item->file_path;
                $error = null;
                // Restrict cleanup to the generated credential directory, never arbitrary paths.
                if (! preg_match('#\Adoctor-documents/[A-Za-z0-9._-]+\z#', $path) || str_contains($path, '..')) {
                    $error = 'unsafe_path';
                } elseif (Document::where('file_path', $path)->exists()) {
                    $error = 'file_still_referenced';
                } else {
                    try {
                        $disk = Storage::disk('documents');
                        if ($disk->exists($path) && ! $disk->delete($path)) {
                            $error = 'storage_failure';
                        }
                    } catch (\Throwable) {
                        $error = 'storage_failure';
                    }
                }
                if ($error !== null) {
                    $item->last_error = $error;
                    $item->save();
                    Log::warning('Private document cleanup deferred', ['cleanup_id' => $item->id, 'reason' => $error]);

                    return false;
                }
                // If this database write fails after file deletion, retry safely sees a missing file.
                $item->delete();

                return true;
            }, 3);
            if (! $ok) {
                $failed++;
            }
        }
        $this->info('Attempted '.$ids->count().' queued cleanups; deferred '.$failed.'.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
