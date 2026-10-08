<?php

use App\Models\Admin;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Rendezvous;
use App\Models\Speciality;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
// Laravel's global exception renderer can return success for standalone scripts.
set_exception_handler(function (Throwable $exception): void {
    fwrite(STDERR, $exception->getMessage().PHP_EOL);
    exit(1);
});
$root = realpath(__DIR__.'/../../storage/app/e2e-runs');
$run = realpath(getenv('SIHATECH_E2E_RUN_DIR') ?: '');
$database = config('database.connections.sqlite.database');
if (getenv('SIHATECH_E2E') !== '1' || ! $app->environment('testing') || $app->configurationIsCached()
    || config('database.default') !== 'sqlite' || ! $root || ! $run || dirname($run) !== $root
    || realpath($database) !== $run.DIRECTORY_SEPARATOR.'database.sqlite' || filesize($database) !== 0
    || realpath(config('filesystems.disks.documents.root')) !== $run.DIRECTORY_SEPARATOR.'private') {
    throw new RuntimeException('Browser tests require a fresh database inside storage/app/e2e-runs.');
}
if (Artisan::call('migrate', ['--force' => true]) !== 0) {
    throw new RuntimeException('Browser-test migrations failed.');
}
$actor = User::create(['nom' => 'Reviewer', 'prenom' => 'E2E', 'email' => 'reviewer@e2e.test',
    'role' => 'admin', 'status' => 'actif', 'password' => 'E2E-only-password-2026!']);
$actor->forceFill(['email_verified_at' => now()])->save();
Admin::create(['user_id' => $actor->id, 'admin_status' => 1]);
$patient = User::create(['nom' => 'Fixture', 'prenom' => 'E2E', 'email' => 'patient@e2e.test',
    'role' => 'patient', 'status' => 'actif', 'password' => 'E2E-only-password-2026!']);
$patient->forceFill(['email_verified_at' => now()])->save();
Patient::create(['user_id' => $patient->id]);
$doctorUser = User::create(['nom' => 'Clinician', 'prenom' => 'E2E', 'email' => 'doctor@e2e.test',
    'role' => 'medecin', 'status' => 'actif', 'password' => 'E2E-only-password-2026!']);
$doctorUser->forceFill(['email_verified_at' => now()])->save();
$speciality = Speciality::create(['nom' => 'E2E General Medicine', 'description' => 'Synthetic browser-test speciality.']);
Doctor::create(['user_id' => $doctorUser->id, 'speciality_id' => $speciality->id,
    'is_verified' => true, 'is_active' => true,
    'horaires' => array_fill_keys(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'], ['09:00-10:00'])]);
// Keep this account separate from administrator status tests. Its user and
// patient IDs intentionally differ to exercise appointment ownership mapping.
$bookingUser = User::create(['nom' => 'Booking', 'prenom' => 'E2E', 'email' => 'booking@e2e.test',
    'role' => 'patient', 'status' => 'actif', 'password' => 'E2E-only-password-2026!']);
$bookingUser->forceFill(['email_verified_at' => now()])->save();
Patient::create(['user_id' => $bookingUser->id]);
// Availability edits use another doctor so browser specifications can run
// independently without changing the booking journey's weekly schedule.
$availabilityUser = User::create(['nom' => 'Schedule', 'prenom' => 'E2E', 'email' => 'availability@e2e.test',
    'role' => 'medecin', 'status' => 'actif', 'password' => 'E2E-only-password-2026!']);
$availabilityUser->forceFill(['email_verified_at' => now()])->save();
$availabilityDoctor = Doctor::create(['user_id' => $availabilityUser->id, 'speciality_id' => $speciality->id,
    'is_verified' => true, 'is_active' => true,
    'horaires' => array_fill_keys(['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'], ['09:00-10:00'])]);
$availabilityPatientUser = User::create(['nom' => 'Schedule Patient', 'prenom' => 'E2E', 'email' => 'availability-patient@e2e.test',
    'role' => 'patient', 'status' => 'actif', 'password' => 'E2E-only-password-2026!']);
$availabilityPatientUser->forceFill(['email_verified_at' => now()])->save();
$availabilityPatient = Patient::create(['user_id' => $availabilityPatientUser->id]);
foreach ([14 => 'en_attente', 15 => 'confirmé'] as $daysAhead => $status) {
    Rendezvous::create(['doctor_id' => $availabilityDoctor->id, 'patient_id' => $availabilityPatient->id,
        'date_heure' => today()->addDays($daysAhead)->setTime(9, 0), 'statut' => $status]);
}
$credentialUser = User::create(['nom' => 'Credentials', 'prenom' => 'E2E', 'email' => 'credentials@e2e.test',
    'role' => 'medecin', 'status' => 'actif', 'password' => 'E2E-only-password-2026!']);
$credentialUser->forceFill(['email_verified_at' => now()])->save();
Doctor::create(['user_id' => $credentialUser->id, 'speciality_id' => $speciality->id,
    'is_verified' => false, 'is_active' => true, 'horaires' => ['lundi' => ['09:00-10:00']]]);
echo "Fresh browser-test fixtures ready.\n";
$securityUser = User::create(['nom' => 'Security', 'prenom' => 'E2E', 'email' => 'security@e2e.test',
    'role' => 'patient', 'status' => 'actif', 'password' => 'E2E-only-password-2026!']);
$securityUser->forceFill(['email_verified_at' => now()])->save();
Patient::create(['user_id' => $securityUser->id]);
