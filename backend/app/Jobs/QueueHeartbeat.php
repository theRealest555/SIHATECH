<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class QueueHeartbeat implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 10;

    public function __construct(public int $sentAt) {}

    public function handle(): void
    {
        // A delayed backlog must not make a stopped or lagging worker look healthy.
        $age = now()->timestamp - $this->sentAt;
        if ($age < 0 || $age > config('operations.heartbeat_max_age')) {
            return;
        }
        if (! Cache::put('ops:heartbeat:queue', $this->sentAt, 300)) {
            throw new \RuntimeException('Queue heartbeat could not be stored.');
        }
    }
}
