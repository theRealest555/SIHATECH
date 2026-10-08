<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $services = [];
        try {
            DB::select('SELECT 1');
            $services['database'] = 'OK';
        } catch (Throwable $exception) {
            report($exception);
            $services['database'] = 'FAILED';
        }

        $key = 'ops:probe:'.Str::uuid();
        try {
            if (! Cache::put($key, 'ready', 30) || Cache::get($key) !== 'ready') {
                throw new \RuntimeException('Readiness cache round trip failed.');
            }
            Cache::forget($key);
            $services['cache'] = 'OK';
        } catch (Throwable $exception) {
            report($exception);
            $services['cache'] = 'FAILED';
        }

        if (config('operations.require_heartbeats')) {
            foreach (['scheduler', 'queue'] as $service) {
                try {
                    $timestamp = Cache::get('ops:heartbeat:'.$service);
                    $age = is_int($timestamp) ? now()->timestamp - $timestamp : null;
                    $services[$service] = $age !== null && $age >= 0 && $age <= config('operations.heartbeat_max_age') ? 'OK' : 'FAILED';
                    if ($service === 'queue' && in_array(config('queue.connections.'.config('queue.default').'.driver'), ['sync', 'null'], true)) {
                        $services[$service] = 'FAILED';
                    }
                } catch (Throwable $exception) {
                    report($exception);
                    $services[$service] = 'FAILED';
                }
            }
        }

        $healthy = ! in_array('FAILED', $services, true);

        return response()->json([
            'status' => $healthy ? 'OK' : 'FAILED',
            'timestamp' => now()->toISOString(),
            'services' => $services,
        ], $healthy ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
