# Readiness, scheduler and queue operations

`GET /health` now checks database connectivity with `SELECT 1` and performs an actual cache write/read/delete round trip. Failed dependencies return HTTP 503 and generic service statuses, with exceptions reported internally. Responses are not cached and this route does not start a browser session. `/up` remains application liveness; it does not prove database, queue or mail availability.

The registered Laravel schedule runs `ops:heartbeat` every minute with a two-minute overlap lock. The command records scheduler activity and dispatches a small probe onto the default connection and queue. A worker records the probe's original dispatch timestamp; probes delayed more than 180 seconds or dated in the future cannot refresh health. An additional five-minute appointment reminder command is guarded by its own feature flag; see [appointment-reminder-verification.md](appointment-reminder-verification.md). The deprecated console kernel no longer contains dormant schedules for missing commands.

Set `OPS_REQUIRE_HEARTBEATS=true` in staging/production after provisioning the scheduler and worker. Then `/health` requires both timestamps to be no more than 180 seconds old, and refuses a sync/null queue as worker proof. The default is false for local development, where readiness checks cover database/cache only. The heartbeat keys expire after five minutes.

Asynchronous queue connections now use `after_commit=true`: transactional jobs and queued notifications wait for commit, and rollback discards them. This does not provide exactly-once mail delivery or make every existing job retry-safe.

## Hosting setup

1. Use the existing migrations for `jobs`, `failed_jobs`, cache and cache locks; this batch adds no migration. Configure `QUEUE_CONNECTION=database` (or a provisioned Redis queue), `CACHE_STORE=database` (or shared Redis), `OPS_REQUIRE_HEARTBEATS=true`, and an environment-specific `CACHE_PREFIX`. The example environment now uses the supported `CACHE_STORE` name. Keep the same cache and clock configuration across API, scheduler and worker processes.
2. Run `php artisan schedule:run` once per minute through the hosting scheduler, from the backend directory, with logs and failure alerts. For example, on Linux with this example installation path:

   ```cron
   * * * * * cd /srv/sihatech/backend && php artisan schedule:run >> /var/log/sihatech-scheduler.log 2>&1
   ```

3. Supervise a long-running worker, for example `php artisan queue:work database --queue=default --sleep=3 --tries=3 --backoff=10 --timeout=60`. Adjust the queue/connection to the configured default. The timeout must stay below the queue's retry interval (database default: 90 seconds). Restart workers on deployment with `php artisan queue:restart`; the process manager must restart exited workers. These practices follow [Laravel's queue documentation](https://laravel.com/framework/docs/12.x/queues#running-the-queue-worker).
4. Check `php artisan schedule:list`, then verify `/health` becomes healthy after a scheduled tick and worker execution. Stop each process separately in staging and confirm HTTP 503 after the freshness window, then confirm recovery. Alert on readiness failures, process exits, failed jobs and queue backlog. Review `php artisan queue:failed` and investigate before replaying a job; replay can duplicate notifications.
5. Run one scheduler per environment, or coordinate the hosting scheduler yourself. Heartbeats measure the default queue and aggregate process activity, not every server, queue or business task. They do not prove email delivery, Stripe delivery or worker capacity. Multi-host production needs a shared cache, synchronized clocks and separate infrastructure monitoring.

Scheduler registration follows [Laravel's scheduling documentation](https://laravel.com/framework/docs/12.x/scheduling). No hosting services were installed or started permanently by this batch.

## Business jobs and backups

The legacy no-show job now performs only a read-only attendance audit and remains unscheduled; `appointments:audit-attendance` provides the same staff-review check. Manual no-show decisions require confirmed past visits and an authorized human action; see [attendance-verification.md](attendance-verification.md). The legacy renewal job performs only a read-only period audit and remains unscheduled; `subscriptions:audit-periods` provides the same operator check. See [subscription-period-verification.md](subscription-period-verification.md) for limits and provider reconciliation. Appointment reminders are implemented with an optional schedule and durable delivery ledger. Notification cleanup, statistics, backup and session-cleanup commands from the old kernel remain unimplemented; removing their dormant registrations does not implement those functions.

Provision encrypted database and private-document backups through the hosting platform, define retention and restore targets, and rehearse restoring both into an isolated environment. No application backup command, remote monitoring account or data-retention deletion was introduced here.

## Verification

Seven new backend tests cover public session-free readiness, database/cache failure responses without exception leakage, required and stale heartbeats, sync queue rejection, delayed/future probes, command/schedule registration, and actual database queue execution. The real worker test also proves that dispatch waits for a committed transaction and is discarded on rollback.

The operations batch passed **261 tests, 1456 assertions** on PHP 8.5.1 / SQLite; after the reminder batch the full suite passed **268 tests, 1499 assertions**. `schedule:list` shows the minute heartbeat and the feature-guarded five-minute reminder command. PHP formatting and Git whitespace checks passed. No frontend changes were made; the prior 96-test frontend suite, build and lint results remain applicable. Redis, production database concurrency, hosted process supervision, external alert delivery and restore drills still require staging verification.
