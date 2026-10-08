<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizeDocuments extends Command
{
    protected $signature = 'documents:privatize {--apply : Copy, verify and remove public copies; otherwise only report}';

    protected $description = 'Move existing doctor credentials from public to private storage';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('documents');
        $failures = 0;
        foreach (Document::cursor() as $document) {
            if (! $public->exists($document->file_path)) {
                continue;
            }
            $this->line('Document '.$document->id.($this->option('apply') ? ': moving' : ': public copy found'));
            if (! $this->option('apply')) {
                continue;
            }
            try {
                $content = $public->get($document->file_path);
                // A conflicting private copy must be reviewed rather than overwritten.
                if ($private->exists($document->file_path) && hash('sha256', $private->get($document->file_path)) !== hash('sha256', $content)) {
                    throw new \RuntimeException('Private copy differs from public copy.');
                }
                if (! $private->exists($document->file_path)) {
                    $private->put($document->file_path, $content);
                }
                if (hash('sha256', $private->get($document->file_path)) !== hash('sha256', $content)) {
                    throw new \RuntimeException('Private copy verification failed.');
                }
                if (! $public->delete($document->file_path)) {
                    throw new \RuntimeException('Public copy could not be removed.');
                }
            } catch (\Throwable $e) {
                $this->error('Document '.$document->id.': '.$e->getMessage());
                $failures++;
            }
        }

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
