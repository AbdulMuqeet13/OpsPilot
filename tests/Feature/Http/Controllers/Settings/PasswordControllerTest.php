<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

it('updates the password when the current one is correct', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->put(route('password.update'), [
            'current_password' => 'password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();
});

it('rejects an incorrect current password', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->put(route('password.update'), [
            'current_password' => 'not-my-password',
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])
        ->assertSessionHasErrors(['current_password' => 'The password is incorrect.']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
