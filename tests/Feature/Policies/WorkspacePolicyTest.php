<?php

use App\Models\User;
use App\Models\Workspace;

test('admins can manage their own workspace settings', function () {
    $admin = User::factory()->create();

    expect($admin->can('manageSettings', $admin->workspace))->toBeTrue();
});

test('agents cannot manage workspace settings', function () {
    $agent = User::factory()->agent()->create();

    expect($agent->can('manageSettings', $agent->workspace))->toBeFalse();
});

test('admins cannot manage another workspace', function () {
    $admin = User::factory()->create();

    expect($admin->can('manageSettings', Workspace::factory()->create()))->toBeFalse();
});
