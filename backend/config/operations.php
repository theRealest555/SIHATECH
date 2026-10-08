<?php

return [
    'appointment_reminders_enabled' => env('APPOINTMENT_REMINDERS_ENABLED', false),
    'require_heartbeats' => env('OPS_REQUIRE_HEARTBEATS', false),
    'heartbeat_max_age' => 180,
];
