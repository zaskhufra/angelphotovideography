<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertSee('Create my account')
        ->assertSee(route('register'))
        ->assertSee('Continue with Google')
        ->assertSee(route('google.redirect'));
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('photographers and administrators can authenticate', function () {
    foreach (['photographer', 'admin'] as $role) {
        $user = User::factory()->create(['role' => $role]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $destination = $role === 'admin' ? 'admin.dashboard' : 'dashboard';
        $response->assertRedirect(route($destination, absolute: false));
        $this->post('/logout');
    }

    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});

test('logout navigation submits a csrf-protected form', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()
        ->assertSee('<form method="POST" action="'.route('logout').'">', false)
        ->assertSee('name="_token"', false)
        ->assertSee('<button type="submit"', false)
        ->assertDontSee('<a href="'.route('logout').'"', false);
});
