<?php

use App\Enums\AiFeature;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('forbids agents', function () {
    actingAs(User::factory()->agent()->create())
        ->get(route('ai-usage.index'))
        ->assertForbidden();
});

it('summarises only the admin\'s own workspace', function () {
    $admin = User::factory()->create();
    AiUsageLog::factory()->for($admin->workspace)->costing(0.25)->create(['feature' => AiFeature::TicketTriage]);
    AiUsageLog::factory()->for($admin->workspace)->failed()->create(['feature' => AiFeature::TicketTriage]);
    AiUsageLog::factory()->for(Workspace::factory())->costing(9.00)->create();

    actingAs($admin)
        ->get(route('ai-usage.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai-usage')
            ->where('summary.todayCostUsd', 0.25)
            ->where('summary.last7DaysCalls', 2)
            ->where('summary.last7DaysFailureRate', 0.5)
            ->where('byFeature.0.label', 'Ticket triage')
            ->where('byFeature.0.calls', 2)
            ->has('recentCalls.data', 2)
            ->where('demoBudget', null));
});

it('shows the shared demo budget in demo mode', function () {
    config(['demo.enabled' => true, 'demo.daily_ai_spend_cap_usd' => 2.00]);
    $admin = User::factory()->create();
    AiUsageLog::factory()->for(Workspace::factory())->costing(0.75)->create();

    actingAs($admin)
        ->get(route('ai-usage.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('demoBudget.capUsd', 2)
            ->where('demoBudget.spentTodayUsd', 0.75));
});

it('shares a paused-AI banner message once the cap is reached', function () {
    config(['demo.enabled' => true, 'demo.daily_ai_spend_cap_usd' => 0.10]);
    AiUsageLog::factory()->costing(0.10)->create();

    actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('aiPaused', fn (?string $message) => str_contains((string) $message, "Today's AI budget")));
});
