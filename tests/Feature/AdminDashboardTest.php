<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('administrators can view account totals and recent accounts', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.openweathermap.org/data/2.5/weather*' => Http::response([
            'name' => 'Arakan',
            'main' => ['temp' => 24.7, 'humidity' => 72],
            'weather' => [['description' => 'light rain']],
            'wind' => ['speed' => 1.8],
        ]),
    ]);
    config([
        'services.openweather.api_key' => 'test-weather-api-key',
        'services.openweather.location' => 'Doroluman, Arakan, Cotabato',
        'services.openweather.units' => 'metric',
    ]);

    User::factory()->create(['role' => 'admin']);
    User::factory()->create(['role' => 'client']);
    User::factory()->create(['role' => 'photographer']);
    $admin = User::factory()->create(['name' => 'Dashboard Admin', 'role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertViewHas('totalUsers', 4)
        ->assertViewHas('clientCount', 1)
        ->assertViewHas('photographerCount', 1)
        ->assertViewHas('adminCount', 2)
        ->assertSee('Dashboard Admin')
        ->assertSee('Arakan')
        ->assertSee('24.7°C')
        ->assertSee('light rain')
        ->assertSee('72%')
        ->assertSee('1.8 m/s')
        ->assertSee('Recent accounts');

    Http::assertSent(function (Request $request): bool {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['q'] ?? null) === 'Doroluman, Arakan, Cotabato'
            && ($query['appid'] ?? null) === 'test-weather-api-key'
            && ($query['units'] ?? null) === 'metric';
    });
});

test('administrators see a fallback when the weather location is not available', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.openweathermap.org/data/2.5/weather*' => Http::response(['message' => 'city not found'], 404),
    ]);
    config([
        'services.openweather.api_key' => 'test-weather-api-key',
        'services.openweather.location' => 'Unknown location',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSee('Weather information is currently unavailable.')
        ->assertDontSee('city not found');
});

test('administrators see a fallback when the weather response is invalid', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.openweathermap.org/data/2.5/weather*' => Http::response([
            'name' => 'Arakan',
            'main' => ['temp' => 'unknown'],
            'weather' => [['description' => 'clear sky']],
            'wind' => ['speed' => 2],
        ]),
    ]);
    config([
        'services.openweather.api_key' => 'test-weather-api-key',
        'services.openweather.location' => 'Doroluman, Arakan, Cotabato',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSee('Weather information is currently unavailable.')
        ->assertDontSee('clear sky');
});

test('administrators see a fallback when the weather service cannot be reached', function () {
    Http::preventStrayRequests();
    Http::fake([
        'api.openweathermap.org/data/2.5/weather*' => Http::failedConnection(),
    ]);
    config([
        'services.openweather.api_key' => 'test-weather-api-key',
        'services.openweather.location' => 'Doroluman, Arakan, Cotabato',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSee('Weather information is currently unavailable.');
});

test('administrators see a fallback when the weather API key is missing', function () {
    Http::preventStrayRequests();
    config([
        'services.openweather.api_key' => '',
        'services.openweather.location' => 'Doroluman, Arakan, Cotabato',
    ]);
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSee('Weather information is currently unavailable.');

    Http::assertNothingSent();
});

test('clients and photographers cannot view the admin dashboard', function (string $role) {
    $user = User::factory()->create(['role' => $role]);

    $response = $this->actingAs($user)->get(route('admin.dashboard'));

    $response->assertForbidden();
})->with(['client', 'photographer']);

test('guests are redirected to login from the admin dashboard', function () {
    $response = $this->get(route('admin.dashboard'));

    $response->assertRedirect(route('login'));
});
