<?php

use App\Models\Doctor;
use App\Models\Leave;
use App\Models\Patient;
use App\Models\Rendezvous;
use App\Models\Speciality;
use App\Models\User;
use App\Services\ProfileRevision;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, $error->getMessage().PHP_EOL);
    exit(1);
});
if (getenv('SIHATECH_CONCURRENCY') !== '1' || ! $app->environment('testing') || $app->configurationIsCached()
    || config('database.default') !== 'mysql' || ! preg_match('/^sihatech_e2e_[a-z0-9]+$/', config('database.connections.mysql.database'))
    || DB::select('SHOW TABLES') !== []) {
    throw new RuntimeException('Use an empty disposable sihatech_e2e_* MySQL database, with SIHATECH_CONCURRENCY=1 and APP_ENV=testing.');
}
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException(Artisan::output());
}
$run = storage_path('app/concurrency-runs/'.bin2hex(random_bytes(8)));
mkdir($run, 0700, true);
$schedule = array_fill_keys(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'], ['09:00-10:00']);
$speciality = Speciality::create(['nom' => 'Concurrency fixture', 'description' => 'Synthetic tests only']);
$doctorUser = User::create(['nom' => 'Doctor', 'prenom' => 'Concurrency', 'email' => 'doctor@concurrency.test', 'role' => 'medecin', 'status' => 'actif', 'password' => 'Synthetic-only-password-2026!']);
$doctorUser->forceFill(['email_verified_at' => now()])->save();
$doctor = Doctor::create(['user_id' => $doctorUser->id, 'speciality_id' => $speciality->id, 'is_verified' => true, 'is_active' => true, 'horaires' => $schedule]);
$doctorToken = $doctorUser->createToken('concurrency')->plainTextToken;
$patients = [];
foreach ([1, 2] as $index) {
    $user = User::create(['nom' => 'Patient'.$index, 'prenom' => 'Concurrency', 'email' => 'patient'.$index.'@concurrency.test', 'role' => 'patient', 'status' => 'actif', 'password' => 'Synthetic-only-password-2026!']);
    $user->forceFill(['email_verified_at' => now()])->save();
    $patients[] = ['profile' => Patient::create(['user_id' => $user->id]), 'token' => $user->createToken('concurrency')->plainTextToken];
}
function race(array $requests, string $run, string $name): array
{
    $barrier = $run.'/'.$name.'.go';
    $processes = [];
    foreach ($requests as $index => $request) {
        $input = $run.'/'.$name.'-'.$index.'.json';
        file_put_contents($input, json_encode($request, JSON_THROW_ON_ERROR));
        $process = new Process([PHP_BINARY, __DIR__.'/worker.php', $input, $barrier], base_path());
        $process->setTimeout(40);
        $process->start();
        $processes[] = [$process, $input];
    }
    try {
        $deadline = microtime(true) + 15;
        foreach ($processes as [$process, $input]) {
            while (! is_file($input.'.ready')) {
                if (! $process->isRunning() || microtime(true) > $deadline) {
                    throw new RuntimeException('Worker startup failed: '.$process->getErrorOutput());
                }
                usleep(10000);
            }
        }
        touch($barrier);
        $results = [];
        foreach ($processes as [$process, $input]) {
            $process->wait();
            if (! $process->isSuccessful() || ! is_file($input.'.result')) {
                throw new RuntimeException('Worker failed: '.$process->getErrorOutput());
            }
            $results[] = json_decode(file_get_contents($input.'.result'), true, 512, JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        foreach ($processes as [$process]) {
            $process->stop();
        }
    }
}
function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
$date = today()->addDays(7)->setTime(9, 0);
$book = fn (int $patient) => ['method' => 'POST', 'url' => '/api/patient/doctors/'.$doctor->id.'/appointments', 'token' => $patients[$patient]['token'], 'data' => ['date_heure' => $date->toDateTimeString()]];
for ($round = 0; $round < 5; $round++) {
    $results = race([$book(0), $book(1)], $run, 'double-booking-'.$round);
    $statuses = array_column($results, 'status');
    sort($statuses);
    check($statuses === [201, 409], 'Double booking must produce exactly one success and one conflict: '.json_encode($statuses));
    check(Rendezvous::where('statut', 'en_attente')->count() === 1, 'Only one appointment may occupy the slot.');
    Rendezvous::query()->update(['statut' => 'annulé']);
}
echo "PASS: five simultaneous same-slot booking races.\n";
foreach (['schedule', 'leave'] as $kind) {
    $doctor->update(['horaires' => $schedule]);
    $other = $kind === 'schedule'
        ? ['method' => 'PUT', 'url' => '/api/doctor/schedule', 'token' => $doctorToken, 'data' => ['schedule' => [], 'expected_schedule_revision' => app(ProfileRevision::class)->schedule($doctor->fresh())]]
        : ['method' => 'POST', 'url' => '/api/doctor/leaves', 'token' => $doctorToken, 'data' => ['start_date' => $date->toDateString(), 'end_date' => $date->toDateString()]];
    $results = race([$book(0), $other], $run, 'booking-versus-'.$kind);
    check(in_array(array_column($results, 'status'), [[201, 409], [409, $kind === 'leave' ? 201 : 200]], true), 'Booking versus '.$kind.' must preserve one winning decision: '.json_encode(array_column($results, 'status')));
    $active = Rendezvous::where('statut', 'en_attente')->count();
    check($active === ($results[0]['status'] === 201 ? 1 : 0), 'Appointment state must match the winning decision.');
    if ($kind === 'leave') {
        check(Leave::count() === ($results[1]['status'] === 201 ? 1 : 0), 'Leave state must match the winning decision.');
    } else {
        check($doctor->fresh()->horaires == ($results[1]['status'] === 200 ? [] : $schedule), 'Schedule state must match the winning decision.');
    }
    Rendezvous::query()->update(['statut' => 'annulé']);
    echo 'PASS: simultaneous booking versus '.$kind.".\n";
}
echo "MySQL concurrency verification passed. Disposable database retained for inspection.\n";
