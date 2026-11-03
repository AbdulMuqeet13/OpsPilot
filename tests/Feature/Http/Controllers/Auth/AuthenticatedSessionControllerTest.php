<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('renders the sign in page', function () {
    get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/login')->where('canRegister', true));
});

it('hides the registration link in demo mode', function () {
    config(['demo.enabled' => true]);

    get(route('login'))
        ->assertInertia(fn (Assert $page) => $page->where('canRegister', false));
});

it('signs the user in with valid credentials', function () {
    $user = User::factory()->create();

    post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));

    assertAuthenticatedAs($user);
});

it('rejects an incorrect password', function () {
    $user = User::factory()->create();

    post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    assertGuest();
});

it('locks the account out after five failed attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
    }

    post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    assertGuest();
});

it('signs the user out', function () {
    actingAs(User::factory()->create())
        ->post(route('logout'))
        ->assertRedirect(route('home'));

    assertGuest();
});

it('redirects guests away from the dashboard', function () {
    get(route('dashboard'))->assertRedirect(route('login'));
});
