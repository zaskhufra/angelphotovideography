<?php

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

test('clients can request a date-only session with their selected photographer', function () {
    $client = User::factory()->create(['role' => 'client']);
    $photographer = User::factory()->create(['role' => 'photographer']);
    $date = now()->addDays(3)->toDateString();

    $response = $this->actingAs($client)->post(route('appointments.store'), [
        'photographer_id' => $photographer->id,
        'shoot_type' => 'photoshoot',
        'appointment_date' => $date,
        'notes' => 'Outdoor family portraits',
    ]);

    $response->assertRedirect(route('appointments.index'));
    $this->assertDatabaseHas('appointments', [
        'client_id' => $client->id,
        'photographer_id' => $photographer->id,
        'shoot_type' => 'photoshoot',
        'status' => 'pending',
        'notes' => 'Outdoor family portraits',
    ]);
    expect(Appointment::query()->sole()->appointment_date->toDateString())->toBe($date);
    expect(Schema::hasColumn('appointments', 'appointment_time'))->toBeFalse();
    expect(Schema::hasColumn('appointments', 'payment_status'))->toBeFalse();
});

test('appointments retain client and photographer details from when they were booked', function () {
    $client = User::factory()->create([
        'name' => 'Client at booking',
        'email' => 'client-at-booking@example.test',
        'role' => 'client',
    ]);
    $photographer = User::factory()->create([
        'name' => 'Photographer at booking',
        'email' => 'photographer-at-booking@example.test',
        'role' => 'photographer',
    ]);
    $appointment = Appointment::factory()->create([
        'client_id' => $client->id,
        'photographer_id' => $photographer->id,
    ]);

    $client->update(['name' => 'Updated client', 'email' => 'updated-client@example.test']);
    $photographer->update(['name' => 'Updated photographer', 'email' => 'updated-photographer@example.test']);

    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'client_name' => 'Client at booking',
        'client_email' => 'client-at-booking@example.test',
        'photographer_name' => 'Photographer at booking',
        'photographer_email' => 'photographer-at-booking@example.test',
    ]);
});

test('appointments refresh participant snapshots when assigned users change', function () {
    $appointment = Appointment::factory()->create();
    $client = User::factory()->create([
        'name' => 'Replacement client',
        'email' => 'replacement-client@example.test',
        'role' => 'client',
    ]);
    $photographer = User::factory()->create([
        'name' => 'Replacement photographer',
        'email' => 'replacement-photographer@example.test',
        'role' => 'photographer',
    ]);

    $appointment->update([
        'client_id' => $client->id,
        'photographer_id' => $photographer->id,
    ]);

    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'client_name' => 'Replacement client',
        'client_email' => 'replacement-client@example.test',
        'photographer_name' => 'Replacement photographer',
        'photographer_email' => 'replacement-photographer@example.test',
    ]);
});

test('clients cannot select an account that is not a photographer', function () {
    $client = User::factory()->create(['role' => 'client']);
    $otherClient = User::factory()->create(['role' => 'client']);

    $response = $this->actingAs($client)->post(route('appointments.store'), [
        'photographer_id' => $otherClient->id,
        'shoot_type' => 'videography',
        'appointment_date' => now()->addDays(3)->toDateString(),
    ]);

    $response->assertSessionHasErrors('photographer_id');
    $this->assertDatabaseCount('appointments', 0);
});

test('clients cannot request an appointment in the past', function () {
    $client = User::factory()->create(['role' => 'client']);
    $photographer = User::factory()->create(['role' => 'photographer']);

    $response = $this->actingAs($client)->post(route('appointments.store'), [
        'photographer_id' => $photographer->id,
        'shoot_type' => 'videography',
        'appointment_date' => now()->subDay()->toDateString(),
    ]);

    $response->assertSessionHasErrors('appointment_date');
    $this->assertDatabaseCount('appointments', 0);
});

test('a photographer cannot receive two active appointments on the same date', function () {
    $photographer = User::factory()->create(['role' => 'photographer']);
    $date = now()->addDays(3)->toDateString();
    Appointment::factory()->create([
        'photographer_id' => $photographer->id,
        'appointment_date' => $date,
        'status' => 'pending',
    ]);
    $client = User::factory()->create(['role' => 'client']);

    $response = $this->actingAs($client)->post(route('appointments.store'), [
        'photographer_id' => $photographer->id,
        'shoot_type' => 'photoshoot',
        'appointment_date' => $date,
    ]);

    $response->assertSessionHasErrors('appointment_date');
    $this->assertDatabaseCount('appointments', 1);
});

test('clients can cancel their own pending appointment', function () {
    $appointment = Appointment::factory()->create();

    $response = $this->actingAs($appointment->client)->patch(route('appointments.cancel', $appointment));

    $response->assertRedirect(route('appointments.index'));
    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'status' => 'cancelled',
    ]);
});

test('clients cannot cancel another clients appointment', function () {
    $appointment = Appointment::factory()->create();
    $otherClient = User::factory()->create(['role' => 'client']);

    $response = $this->actingAs($otherClient)->patch(route('appointments.cancel', $appointment));

    $response->assertForbidden();
    $this->assertDatabaseHas('appointments', [
        'id' => $appointment->id,
        'status' => 'pending',
    ]);
});

test('assigned photographers can confirm and complete appointments', function () {
    $appointment = Appointment::factory()->create();
    $photographer = $appointment->photographer;

    $this->actingAs($photographer)
        ->patch(route('appointments.status.update', $appointment), ['status' => 'confirmed'])
        ->assertRedirect(route('appointments.index'));
    $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'confirmed']);

    $this->patch(route('appointments.status.update', $appointment), ['status' => 'completed'])
        ->assertRedirect(route('appointments.index'));
    $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'completed']);
});

test('photographers cannot manage appointments assigned to another photographer', function () {
    $appointment = Appointment::factory()->create();
    $otherPhotographer = User::factory()->create(['role' => 'photographer']);

    $response = $this->actingAs($otherPhotographer)
        ->patch(route('appointments.status.update', $appointment), ['status' => 'confirmed']);

    $response->assertForbidden();
    $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'pending']);
});

test('clients and photographers only see appointments connected to them', function () {
    $firstAppointment = Appointment::factory()->create();
    $secondAppointment = Appointment::factory()->create();

    $this->actingAs($firstAppointment->client)
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee($firstAppointment->photographer->name)
        ->assertDontSee($secondAppointment->client->email);

    $this->actingAs($firstAppointment->photographer)
        ->get(route('appointments.index'))
        ->assertOk()
        ->assertSee($firstAppointment->client->name)
        ->assertDontSee($secondAppointment->client->email);
});

test('photographers cannot access the client booking form', function () {
    $photographer = User::factory()->create(['role' => 'photographer']);

    $response = $this->actingAs($photographer)->get(route('appointments.create'));

    $response->assertForbidden();
});

test('clients can open the appointment form without time or payment fields', function () {
    $client = User::factory()->create(['role' => 'client']);
    User::factory()->create(['role' => 'photographer']);

    $response = $this->actingAs($client)->get(route('appointments.create'));

    $response->assertOk()
        ->assertSee('type="date"', false)
        ->assertDontSee('type="time"', false)
        ->assertDontSee('name="payment', false)
        ->assertSee('No payment is collected now.');
});
