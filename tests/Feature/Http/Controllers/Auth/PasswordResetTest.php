<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\post;

it('emails a reset link to a registered user', function () {
    Notification::fake();
    $user = User::factory()->create();

    post(route('password.email'), ['email' => $user->email])
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('reports the same status for an unknown email', function () {
    Notification::fake();

    post(route('password.email'), ['email' => 'nobody@example.com'])
        ->assertSessionHas('status', 'If that email has an account, a reset link is on its way.');

    Notification::assertNothingSent();
});

it('resets the password with a valid token', function () {
    Notification::fake();
    $user = User::factory()->create();

    post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        post(route('password.store'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'brand-new-password',
            'password_confirmation' => 'brand-new-password',
        ])->assertRedirect(route('login'));

        return true;
    });

    expect(Hash::check('brand-new-password', $user->fresh()->password))->toBeTrue();
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create();

    post(route('password.store'), [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'brand-new-password',
        'password_confirmation' => 'brand-new-password',
    ])->assertSessionHasErrors(['email' => 'This password reset token is invalid.']);
});
