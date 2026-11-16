<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

test('only administrator credentials can issue scoped bearer tokens', function () {
    $admin = User::factory()->create([
        'email' => 'admin@example.test',
        'password' => 'admin-secret',
        'role' => 'admin',
    ]);
    $client = User::factory()->create([
        'email' => 'client@example.test',
        'password' => 'client-secret',
        'role' => 'client',
    ]);

    $adminResponse = $this->postJson('/api/auth/token', [
        'email' => $admin->email,
        'password' => 'admin-secret',
        'device_name' => 'Postman',
    ]);
    $clientResponse = $this->postJson('/api/auth/token', [
        'email' => $client->email,
        'password' => 'client-secret',
        'device_name' => 'Postman',
    ]);

    $adminResponse->assertCreated()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('abilities.0', 'appointments:read');
    $clientResponse->assertForbidden();

    $plainTextToken = $adminResponse->json('token');
    $storedToken = PersonalAccessToken::query()->where('tokenable_id', $admin->id)->sole();

    expect($storedToken->token)->toBe(hash('sha256', explode('|', $plainTextToken, 2)[1]))
        ->and($storedToken->abilities)->toContain('appointments:read', 'appointments:write', 'appointments:delete', 'token:revoke')
        ->and($storedToken->expires_at)->not->toBeNull();
    $this->assertDatabaseCount('personal_access_tokens', 1);
});

test('invalid credentials cannot issue a bearer token', function () {
    $admin = User::factory()->create([
        'email' => 'admin@example.test',
        'password' => 'admin-secret',
        'role' => 'admin',
    ]);

    $response = $this->postJson('/api/auth/token', [
        'email' => $admin->email,
        'password' => 'incorrect',
        'device_name' => 'Postman',
    ]);

    $response->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid credentials.');
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

test('appointment CRUD requires an admin bearer token and its matching ability', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $client = User::factory()->create(['role' => 'client']);
    $photographer = User::factory()->create(['role' => 'photographer']);
    $appointmentDate = now()->addDays(3)->toDateString();

    $tokenResponse = $this->postJson('/api/auth/token', [
        'email' => $admin->email,
        'password' => 'password',
        'device_name' => 'Postman',
    ]);
    $tokenResponse->assertCreated();
    $token = $tokenResponse->json('token');
    $this->withHeader('Authorization', 'Bearer '.$token);

    $created = $this->postJson('/api/appointment', [
        'client_id' => $client->id,
        'photographer_id' => $photographer->id,
        'shoot_type' => 'photography',
        'appointment_date' => $appointmentDate,
        'status' => 'pending',
        'notes' => 'Family portraits',
    ]);

    $created->assertCreated()
        ->assertJsonPath('data.client.id', $client->id)
        ->assertJsonPath('data.photographer.id', $photographer->id)
        ->assertJsonPath('data.shoot_type', 'photography')
        ->assertJsonPath('data.status', 'pending');

    $appointmentId = $created->json('data.id');
    $created->assertJsonPath('message', 'APPOINTMENT '.$appointmentId.' HAVE BEEN CREATED');
    $cloned = $this->postJson('/api/appointment', [
        'data' => [[
            'id' => $appointmentId,
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
            ],
            'photographer' => [
                'id' => $photographer->id,
                'name' => $photographer->name,
                'email' => $photographer->email,
            ],
            'shoot_type' => 'photography',
            'appointment_date' => $appointmentDate,
            'status' => 'pending',
            'notes' => 'Family portraits',
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]],
        'links' => ['first' => 'ignored'],
        'meta' => ['total' => 1],
    ]);

    $cloned->assertCreated()
        ->assertJsonPath('data.client.id', $client->id)
        ->assertJsonPath('data.photographer.id', $photographer->id)
        ->assertJsonPath('data.appointment_date', $appointmentDate);

    expect($cloned->json('data.id'))->not->toBe($appointmentId);
    $this->assertDatabaseCount('appointments', 2);

    $this->getJson('/api/appointments')
        ->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonFragment(['id' => $appointmentId]);
    $this->getJson('/api/appointments/'.$appointmentId)
        ->assertOk()
        ->assertJsonPath('data.notes', 'Family portraits');

    $this->putJson('/api/appointments/'.$appointmentId, [
        'data' => [[
            'id' => $appointmentId,
            'client' => ['id' => $client->id],
            'photographer' => ['id' => $photographer->id],
            'shoot_type' => 'photography',
            'appointment_date' => $appointmentDate,
            'status' => 'confirmed',
            'notes' => 'Updated notes',
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ]],
        'links' => ['first' => 'ignored'],
        'meta' => ['total' => 2],
    ])->assertOk()
        ->assertJsonPath('data.shoot_type', 'photography')
        ->assertJsonPath('data.status', 'confirmed')
        ->assertJsonPath('data.appointment_date', $appointmentDate)
        ->assertJsonPath('message', 'APPOINTMENT '.$appointmentId.' HAVE BEEN UPDATED');

    $this->patchJson('/api/appointments/'.$appointmentId, [
        'data' => [[
            'id' => $appointmentId,
            'notes' => 'Patched notes',
        ]],
    ])->assertOk()
        ->assertJsonPath('data.notes', 'Patched notes')
        ->assertJsonPath('message', 'APPOINTMENT '.$appointmentId.' HAVE BEEN UPDATED');

    $this->deleteJson('/api/appointments/'.$appointmentId)
        ->assertOk()
        ->assertJsonPath('message', 'Appointment have been deleted');
    $this->assertDatabaseMissing('appointments', ['id' => $appointmentId]);
});

test('clients and photographers cannot access the appointments API', function (string $role) {
    Sanctum::actingAs(User::factory()->create(['role' => $role]), ['*']);
    $this->getJson('/api/appointments')->assertForbidden();
})->with(['client', 'photographer']);

test('read-only bearer tokens cannot create appointments', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $readOnlyToken = $admin->createToken('Read-only test token', ['appointments:read']);
    $this->withHeader('Authorization', 'Bearer '.$readOnlyToken->plainTextToken)
        ->postJson('/api/appointments')
        ->assertForbidden();
});

test('appointment API returns unauthorized without a bearer token', function () {
    $this->getJson('/api/appointments')->assertUnauthorized();
});

test('admins can revoke the current bearer token', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $token = $admin->createToken('Postman', ['token:revoke', 'appointments:read']);
    $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken);

    $this->deleteJson('/api/auth/token')
        ->assertOk()
        ->assertJsonPath('message', 'API token revoked.');

    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    Auth::forgetGuards();
    $this->getJson('/api/user')->assertUnauthorized();
});
