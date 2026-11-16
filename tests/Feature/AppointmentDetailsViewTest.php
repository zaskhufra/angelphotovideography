<?php

use App\Models\Appointment;
use Illuminate\Support\Facades\DB;

test('appointment details view joins client and photographer names and emails', function () {
    $appointment = Appointment::factory()->create();

    $details = DB::table('appointment_details')
        ->where('appointment_id', $appointment->id)
        ->first();

    expect($details)->not->toBeNull()
        ->and($details->client_id)->toBe($appointment->client_id)
        ->and($details->client_name)->toBe($appointment->client->name)
        ->and($details->client_email)->toBe($appointment->client->email)
        ->and($details->photographer_id)->toBe($appointment->photographer_id)
        ->and($details->photographer_name)->toBe($appointment->photographer->name)
        ->and($details->photographer_email)->toBe($appointment->photographer->email)
        ->and($details->shoot_type)->toBe($appointment->shoot_type)
        ->and($details->status)->toBe($appointment->status);
});
