<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\post;

it('returns 404 when demo mode is off', function () {
    config(['demo.enabled' => false]);

    post(route('demo.store', 'admin'))->assertNotFound();

    expect(Workspace::count())->toBe(0);
});

it('creates a private sandbox and signs the visitor in with the chosen role', function (UserRole $role) {
    config(['demo.enabled' => true, 'demo.sandbox.lifetime_hours' => 24]);
    $this->freezeSecond();

    post(route('demo.store', $role->value))->assertRedirect(route('dashboard'));

    $workspace = Workspace::sole();

    expect($workspace->is_sandbox)->toBeTrue()
        ->and($workspace->name)->toBe('Oakline Home')
        ->and($workspace->expires_at->equalTo(now()->addHours(24)))->toBeTrue()
        ->and($workspace->users()->pluck('role')->all())->toEqualCanonicalizing([UserRole::Admin, UserRole::Agent])
        ->and(auth()->user()->role)->toBe($role)
        ->and(auth()->user()->workspace_id)->toBe($workspace->id);
})->with([
    'admin' => UserRole::Admin,
    'agent' => UserRole::Agent,
]);

it('gives every visitor a separate sandbox', function () {
    config(['demo.enabled' => true]);

    post(route('demo.store', 'admin'));
    post(route('logout'));
    post(route('demo.store', 'admin'));

    expect(Workspace::count())->toBe(2);
});

it('returns 404 for an unknown role', function () {
    config(['demo.enabled' => true]);

    post(route('demo.store', 'owner'))->assertNotFound();
});

it('stops creating sandboxes once the per-IP hourly limit is reached', function () {
    config(['demo.enabled' => true, 'demo.sandbox.max_per_ip_per_hour' => 2]);

    foreach (range(1, 2) as $attempt) {
        post(route('demo.store', 'agent'));
        post(route('logout'));
    }

    post(route('demo.store', 'agent'))
        ->assertSessionHasErrors(['demo' => 'You have started several demos in the last hour. Please try again a little later.']);

    expect(Workspace::count())->toBe(2);
    assertGuest();
});

it('redirects signed-in users away from the demo route', function () {
    config(['demo.enabled' => true]);

    actingAs(User::factory()->create())
        ->post(route('demo.store', 'admin'))
        ->assertRedirect(route('dashboard'));

    expect(Workspace::count())->toBe(1);
});

it('fills the new sandbox with the Oakline Home sample data', function () {
    config(['demo.enabled' => true]);

    post(route('demo.store', 'agent'));

    $workspace = Workspace::sole();

    expect($workspace->tickets()->count())->toBe(40)
        ->and($workspace->leads()->count())->toBe(25)
        ->and($workspace->aiSuggestions()->whereNotNull('decided_by')->pluck('decided_by')->unique()->diff($workspace->users()->pluck('id'))->all())->toBe([]);
});
