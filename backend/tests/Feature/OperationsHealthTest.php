<?php

namespace Tests\Feature;

use App\Jobs\QueueHeartbeat;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OperationsHealthTest extends TestCase
{
    use DatabaseMigrations;

    public function test_readiness_checks_dependencies_without_starting_a_session(): void
    {
        $response = $this->getJson('/health')->assertOk()->assertJsonPath('services.database', 'OK')->assertJsonPath('services.cache', 'OK');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertEmpty($response->headers->getCookies());
        $this->get('/up')->assertOk();
    }

    public function test_database_failure_is_unhealthy_without_exposing_exception_details(): void
    {
        $database = DB::getFacadeRoot();
        DB::partialMock()->shouldReceive('select')->with('SELECT 1')->once()->andThrow(new \RuntimeException('private database secret'));
        try {
            $this->getJson('/health')->assertStatus(503)->assertJsonPath('services.database', 'FAILED')->assertJsonPath('services.cache', 'OK')->assertDontSee('private database secret');
        } finally {
            DB::swap($database);
        }
    }

    public function test_cache_failure_is_unhealthy_without_exposing_exception_details(): void
    {
        Cache::partialMock()->shouldReceive('put')->once()->andThrow(new \RuntimeException('private cache secret'));
        $this->getJson('/health')->assertStatus(503)->assertJsonPath('services.cache', 'FAILED')->assertDontSee('private cache secret');
    }

    public function test_required_heartbeats_must_be_recent_and_from_an_async_queue(): void
    {
        config(['operations.require_heartbeats' => true, 'queue.default' => 'database']);
        $this->getJson('/health')->assertStatus(503)->assertJsonPath('services.scheduler', 'FAILED')->assertJsonPath('services.queue', 'FAILED');
        Cache::put('ops:heartbeat:scheduler', now()->timestamp, 300);
        (new QueueHeartbeat(now()->timestamp))->handle();
        $this->getJson('/health')->assertOk()->assertJsonPath('services.queue', 'OK');
        config(['queue.default' => 'sync']);
        $this->getJson('/health')->assertStatus(503)->assertJsonPath('services.queue', 'FAILED');
        config(['queue.default' => 'database']);
        $this->travel(181)->seconds();
        $this->getJson('/health')->assertStatus(503)->assertJsonPath('services.scheduler', 'FAILED')->assertJsonPath('services.queue', 'FAILED');
    }

    public function test_delayed_or_future_queue_probes_cannot_refresh_worker_health(): void
    {
        (new QueueHeartbeat(now()->subSeconds(181)->timestamp))->handle();
        (new QueueHeartbeat(now()->addSeconds(1)->timestamp))->handle();
        $this->assertNull(Cache::get('ops:heartbeat:queue'));
    }

    public function test_heartbeat_command_is_registered_and_dispatches_the_worker_probe(): void
    {
        $this->freezeTime();
        Queue::fake();
        $this->artisan('ops:heartbeat')->assertSuccessful();
        $this->assertSame(now()->timestamp, Cache::get('ops:heartbeat:scheduler'));
        Queue::assertPushed(QueueHeartbeat::class);
        $events = array_values(array_filter(app(Schedule::class)->events(), fn ($event) => str_contains($event->command, 'ops:heartbeat')));
        $this->assertCount(1, $events);
        $this->assertStringContainsString('ops:heartbeat', $events[0]->command);
        $this->assertSame('* * * * *', $events[0]->expression);
        $this->assertTrue($events[0]->withoutOverlapping);
    }

    public function test_database_worker_executes_committed_probe_and_rollback_dispatch_is_discarded(): void
    {
        $this->freezeTime();
        config(['queue.default' => 'database']);
        DB::transaction(function () {
            QueueHeartbeat::dispatch(now()->timestamp);
            $this->assertDatabaseCount('jobs', 0);
        });
        $this->assertDatabaseCount('jobs', 1);
        try {
            DB::transaction(function () {
                QueueHeartbeat::dispatch(now()->timestamp);
                throw new \RuntimeException('rollback fixture');
            });
        } catch (\RuntimeException $exception) {
            $this->assertSame('rollback fixture', $exception->getMessage());
        }
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1, '--timeout' => 10])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertSame(now()->timestamp, Cache::get('ops:heartbeat:queue'));
    }
}
