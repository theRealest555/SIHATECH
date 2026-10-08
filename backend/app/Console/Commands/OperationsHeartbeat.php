<?php

namespace App\Console\Commands;

use App\Jobs\QueueHeartbeat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class OperationsHeartbeat extends Command
{
    protected $signature = 'ops:heartbeat';

    protected $description = 'Record scheduler activity and dispatch a queue worker probe';

    public function handle(): int
    {
        $timestamp = now()->timestamp;
        if (! Cache::put('ops:heartbeat:scheduler', $timestamp, 300)) {
            throw new \RuntimeException('Scheduler heartbeat could not be stored.');
        }
        QueueHeartbeat::dispatch($timestamp);

        return self::SUCCESS;
    }
}
