<?php

use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

it('forbids agents', function () {
    actingAs(User::factory()->agent()->create())->post(route('api-key.store'))->assertForbidden();
});

it('flashes the new key once and stores only its hash', function () {
    Str::createRandomStringsUsing(fn (): string => 'abcdefghijklmnopqrstuvwxyz0123456789ABCD');
    $admin = User::factory()->create();

    actingAs($admin)
        ->post(route('api-key.store'))
        ->assertInertiaFlash('newApiKey', 'ops_abcdefghijklmnopqrstuvwxyz0123456789ABCD');

    expect($admin->workspace->fresh())
        ->api_key_hash->toBe(hash('sha256', 'ops_abcdefghijklmnopqrstuvwxyz0123456789ABCD'))
        ->api_key_last_four->toBe('ABCD');

    Str::createRandomStringsNormally();
});

it('revokes the key', function () {
    $admin = User::factory()->create();
    actingAs($admin)->post(route('api-key.store'));

    actingAs($admin)->delete(route('api-key.destroy'))->assertRedirect();

    expect($admin->workspace->fresh()->api_key_hash)->toBeNull();
});
