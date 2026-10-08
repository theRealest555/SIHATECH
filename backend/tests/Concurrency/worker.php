<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
if (getenv('SIHATECH_CONCURRENCY') !== '1' || ! $app->environment('testing')
    || config('database.default') !== 'mysql' || ! preg_match('/^sihatech_e2e_[a-z0-9]+$/', config('database.connections.mysql.database'))) {
    fwrite(STDERR, "A disposable MySQL test database is required.\n");
    exit(1);
}
$input = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
// Announce readiness only after application startup; parent releases both workers.
touch($argv[1].'.ready');
$deadline = microtime(true) + 15;
while (! is_file($argv[2])) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "Concurrency barrier timed out.\n");
        exit(1);
    }
    usleep(10000);
}
$request = Request::create($input['url'], $input['method'], [], [], [], [
    'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
    'HTTP_AUTHORIZATION' => 'Bearer '.$input['token'],
], json_encode($input['data'], JSON_THROW_ON_ERROR));
$response = $kernel->handle($request);
file_put_contents($argv[1].'.result', json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)], JSON_THROW_ON_ERROR));
$kernel->terminate($request, $response);
