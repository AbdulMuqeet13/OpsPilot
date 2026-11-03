<?php

use App\Enums\UserRole;
use App\Models\User;

use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('creates a workspace with the new user as its admin', function () {
    post(route('register.store'), [
        'name' => 'Jordan Pike',
        'company_name' => 'Brightwater Supply',
        'email' => 'jordan@brightwater.example',
        'password' => 'a-long-password',
        'password_confirmation' => 'a-long-password',
    ])->assertRedirect(route('dashboard'));

    $user = User::where('email', 'jordan@brightwater.example')->sole();

    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->workspace->name)->toBe('Brightwater Supply')
        ->and($user->workspace->brand_name)->toBe('Brightwater Supply')
        ->and($user->workspace->support_email)->toBe('jordan@brightwater.example')
        ->and($user->workspace->is_sandbox)->toBeFalse();

    assertAuthenticatedAs($user);
});

it('requires every field', function () {
    post(route('register.store'))
        ->assertSessionHasErrors(['name', 'company_name', 'email', 'password']);
});

it('rejects an email that is already registered', function () {
    $existing = User::factory()->create();

    post(route('register.store'), [
        'name' => 'Jordan Pike',
        'company_name' => 'Brightwater Supply',
        'email' => $existing->email,
        'password' => 'a-long-password',
        'password_confirmation' => 'a-long-password',
    ])->assertSessionHasErrors(['email' => 'The email has already been taken.']);
});

it('returns 404 for registration in demo mode', function () {
    config(['demo.enabled' => true]);

    get(route('register'))->assertNotFound();
    post(route('register.store'))->assertNotFound();
});

it('falls back to a safe brand color when the configured default is invalid', function () {
    config(['opspilot.default_brand.primary_color' => '']);

    post(route('register.store'), [
        'name' => 'Jordan Pike',
        'company_name' => 'Brightwater Supply',
        'email' => 'jordan@brightwater.example',
        'password' => 'a-long-password',
        'password_confirmation' => 'a-long-password',
    ]);

    expect(User::sole()->workspace->primary_color)->toBe('#4f46e5');

    get(route('home'))->assertOk();
});
