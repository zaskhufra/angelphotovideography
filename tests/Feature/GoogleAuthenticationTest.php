<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

function configureGoogleOAuth(): void
{
    config([
        'services.google.client_id' => 'test-google-client-id',
        'services.google.client_secret' => 'test-google-client-secret',
        'services.google.redirect' => 'http://127.0.0.1:8000/auth/google/callback',
    ]);
}

function fakeGoogleProfile(array $profile): void
{
    Http::preventStrayRequests();
    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access-token']),
        'https://openidconnect.googleapis.com/v1/userinfo' => Http::response($profile),
    ]);
}

test('google redirect reports missing credentials', function () {
    config([
        'services.google.client_id' => '',
        'services.google.client_secret' => '',
    ]);

    $response = $this->get(route('google.redirect'));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');
});

test('registration page offers google signup', function () {
    $response = $this->get(route('register'));

    $response->assertOk()
        ->assertSee('Sign up with Google')
        ->assertSee(route('google.redirect'));
});

test('google redirect includes the callback and a session-bound state', function () {
    configureGoogleOAuth();

    $response = $this->get(route('google.redirect'));
    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $response->assertRedirect();
    expect(parse_url($location, PHP_URL_HOST))->toBe('accounts.google.com')
        ->and($query['client_id'])->toBe('test-google-client-id')
        ->and($query['redirect_uri'])->toBe('http://127.0.0.1:8000/auth/google/callback')
        ->and($query['state'])->toBe($response->getSession()->get('google_oauth_states')[0]);
});

test('google callback accepts the original state when multiple sign-in attempts are open', function () {
    configureGoogleOAuth();
    $firstRedirect = $this->get(route('google.redirect'));
    parse_str((string) parse_url((string) $firstRedirect->headers->get('Location'), PHP_URL_QUERY), $firstQuery);
    $secondRedirect = $this->get(route('google.redirect'));
    parse_str((string) parse_url((string) $secondRedirect->headers->get('Location'), PHP_URL_QUERY), $secondQuery);
    fakeGoogleProfile([
        'sub' => 'concurrent-google-user',
        'email' => 'concurrent@example.com',
        'email_verified' => true,
        'name' => 'Concurrent User',
    ]);

    $response = $this->get(route('google.callback', [
        'state' => $firstQuery['state'],
        'code' => 'authorization-code',
    ]));

    $response->assertRedirect(route('google.register'));
    expect(session('google_oauth_states'))
        ->toContain($secondQuery['state'])
        ->not->toContain($firstQuery['state']);
});

test('new google users are sent to registration without saving an account yet', function () {
    configureGoogleOAuth();
    fakeGoogleProfile([
        'sub' => 'google-user-123',
        'email' => 'new-client@example.com',
        'email_verified' => true,
        'name' => 'Google Client',
    ]);

    $response = $this->withSession(['google_oauth_states' => ['valid-state']])
        ->get(route('google.callback', ['state' => 'valid-state', 'code' => 'authorization-code']));

    $response->assertRedirect(route('google.register'));
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'new-client@example.com']);
    expect(session('pending_google_registration'))->toBe([
        'google_id' => 'google-user-123',
        'email' => 'new-client@example.com',
        'name' => 'Google Client',
    ]);
});

test('google registration displays provider name and email as readonly', function () {
    $response = $this->withSession(['pending_google_registration' => [
        'google_id' => 'google-user-123',
        'email' => 'new-client@example.com',
        'name' => 'Google Client',
    ]])->get(route('google.register'));

    $response->assertOk()
        ->assertSee('value="Google Client" readonly', false)
        ->assertSee('value="new-client@example.com" readonly', false)
        ->assertSee('name="role"', false)
        ->assertSee('name="password"', false)
        ->assertDontSee('name="google_email"', false);
});

test('google registration saves provider identity and chosen role and password', function () {
    $response = $this->withSession(['pending_google_registration' => [
        'google_id' => 'google-user-123',
        'email' => 'new-client@example.com',
        'name' => 'Google Client',
    ]])->post(route('google.register.store'), [
        'role' => 'photographer',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
        'name' => 'Forged Name',
        'email' => 'forged@example.com',
        'google_id' => 'forged-google-id',
    ]);

    $user = User::query()->where('email', 'new-client@example.com')->firstOrFail();
    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'name' => 'Google Client',
        'email' => 'new-client@example.com',
        'google_id' => 'google-user-123',
        'role' => 'photographer',
    ]);
    expect(Hash::check('new-password', $user->password))->toBeTrue();
});

test('google registration cannot assign the admin role', function () {
    $response = $this->withSession(['pending_google_registration' => [
        'google_id' => 'google-user-456',
        'email' => 'new-client@example.com',
        'name' => 'Google Client',
    ]])->post(route('google.register.store'), [
        'role' => 'admin',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertSessionHasErrors('role');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'new-client@example.com']);
});

test('google registration requires a pending oauth identity', function () {
    $response = $this->post(route('google.register.store'), [
        'role' => 'client',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');
    $this->assertGuest();
});

test('google sign-in logs in an already registered user by google id', function () {
    configureGoogleOAuth();
    $user = User::factory()->create([
        'email' => 'linked@example.com',
        'google_id' => 'known-google-user',
        'role' => 'client',
    ]);
    fakeGoogleProfile([
        'sub' => 'known-google-user',
        'email' => 'linked@example.com',
        'email_verified' => true,
        'name' => $user->name,
    ]);

    $response = $this->withSession(['google_oauth_states' => ['valid-state']])
        ->get(route('google.callback', ['state' => 'valid-state', 'code' => 'authorization-code']));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('users', 1);
});

test('google sign-in links a matching verified email without changing its role', function () {
    configureGoogleOAuth();
    $user = User::factory()->create([
        'email' => 'photographer@example.com',
        'role' => 'photographer',
    ]);
    fakeGoogleProfile([
        'sub' => 'google-photographer-456',
        'email' => 'photographer@example.com',
        'email_verified' => true,
        'name' => $user->name,
    ]);

    $response = $this->withSession(['google_oauth_states' => ['valid-state']])
        ->get(route('google.callback', ['state' => 'valid-state', 'code' => 'authorization-code']));

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'google_id' => 'google-photographer-456',
        'role' => 'photographer',
    ]);
});

test('google callback rejects an invalid state without making HTTP requests', function () {
    Http::preventStrayRequests();

    $response = $this->withSession(['google_oauth_states' => ['expected-state']])
        ->get(route('google.callback', ['state' => 'wrong-state', 'code' => 'authorization-code']));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');
    $this->assertGuest();
    Http::assertNothingSent();
});

test('google sign-in rejects accounts without a verified email', function () {
    configureGoogleOAuth();
    fakeGoogleProfile([
        'sub' => 'unverified-google-user',
        'email' => 'unverified@example.com',
        'email_verified' => false,
        'name' => 'Unverified User',
    ]);

    $response = $this->withSession(['google_oauth_states' => ['valid-state']])
        ->get(route('google.callback', ['state' => 'valid-state', 'code' => 'authorization-code']));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');
    $this->assertGuest();
    $this->assertDatabaseMissing('users', ['email' => 'unverified@example.com']);
});

test('google sign-in rejects an email linked to a different google account', function () {
    configureGoogleOAuth();
    User::factory()->create([
        'email' => 'linked@example.com',
        'google_id' => 'original-google-id',
    ]);
    fakeGoogleProfile([
        'sub' => 'different-google-id',
        'email' => 'linked@example.com',
        'email_verified' => true,
        'name' => 'Conflicting User',
    ]);

    $response = $this->withSession(['google_oauth_states' => ['valid-state']])
        ->get(route('google.callback', ['state' => 'valid-state', 'code' => 'authorization-code']));

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors('google');
    $this->assertGuest();
    $this->assertDatabaseHas('users', [
        'email' => 'linked@example.com',
        'google_id' => 'original-google-id',
    ]);
});

test('google redirect prefers the current local host when loopback aliases differ', function () {
    configureGoogleOAuth();
    config(['services.google.redirect' => 'http://127.0.0.1:8000/auth/google/callback']);

    $response = $this->get('http://localhost:8000/auth/google/redirect');

    $location = (string) $response->headers->get('Location');
    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    expect($query['redirect_uri'])->toBe('http://localhost:8000/auth/google/callback');
});

test('google token exchange uses the configured callback and auth code', function () {
    configureGoogleOAuth();
    fakeGoogleProfile([
        'sub' => 'google-user-789',
        'email' => 'exchange@example.com',
        'email_verified' => true,
        'name' => 'Exchange User',
    ]);

    $this->withSession(['google_oauth_states' => ['valid-state']])
        ->get(route('google.callback', ['state' => 'valid-state', 'code' => 'authorization-code']));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/token'
        && $request['code'] === 'authorization-code'
        && $request['redirect_uri'] === 'http://127.0.0.1:8000/auth/google/callback'
        && $request['client_id'] === 'test-google-client-id');
});
