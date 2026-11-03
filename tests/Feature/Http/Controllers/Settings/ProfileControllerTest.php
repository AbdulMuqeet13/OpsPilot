<?php

use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('renders the profile page', function () {
    actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('settings/profile')->where('isSandboxAccount', false));
});

it('flags sandbox accounts so the password form is hidden', function () {
    $user = User::factory()->for(Workspace::factory()->sandbox())->create();

    actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('isSandboxAccount', true));
});

it('updates the name and email', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Riley Chen', 'email' => 'riley@example.com'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($user->fresh())
        ->name->toBe('Riley Chen')
        ->email->toBe('riley@example.com');
});

it('rejects an email used by another account', function () {
    $other = User::factory()->create();

    actingAs(User::factory()->create())
        ->patch(route('profile.update'), ['name' => 'Riley Chen', 'email' => $other->email])
        ->assertSessionHasErrors(['email' => 'The email has already been taken.']);
});

it('does not let users change their own role', function () {
    $agent = User::factory()->agent()->create();

    actingAs($agent)->patch(route('profile.update'), [
        'name' => 'Riley Chen',
        'email' => $agent->email,
        'role' => 'admin',
        'workspace_id' => 999,
    ]);

    expect($agent->fresh()->isAdmin())->toBeFalse()
        ->and($agent->fresh()->workspace_id)->toBe($agent->workspace_id);
});
