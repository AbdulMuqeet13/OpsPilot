<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

it('forbids agents', function () {
    actingAs(User::factory()->agent()->create())->get(route('lead-profile.edit'))->assertForbidden();
    actingAs(User::factory()->agent()->create())->put(route('lead-profile.update'), ['ideal_customer_profile' => 'x'])->assertForbidden();
});

it('saves the ideal customer and offer', function () {
    $admin = User::factory()->create();

    actingAs($admin)->put(route('lead-profile.update'), [
        'ideal_customer_profile' => '  Hotels furnishing 10+ rooms.  ',
        'offer_summary' => '',
    ])->assertSessionHasNoErrors();

    expect($admin->workspace->fresh())
        ->ideal_customer_profile->toBe('Hotels furnishing 10+ rooms.')
        ->offer_summary->toBeNull();
});

it('limits the description length', function () {
    actingAs(User::factory()->create())
        ->put(route('lead-profile.update'), ['ideal_customer_profile' => str_repeat('a', 2001)])
        ->assertSessionHasErrors('ideal_customer_profile');
});
