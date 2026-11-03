<?php

use App\Enums\AiFailureReason;
use App\Enums\AiFeature;
use App\Enums\BriefTrigger;
use App\Jobs\GenerateDailyBrief;
use App\Models\AiFeatureSetting;
use App\Models\DailyBrief;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('shows the latest brief and the history for the user\'s workspace only', function () {
    $user = User::factory()->agent()->create();
    DailyBrief::factory()->for($user->workspace)->create(['brief_date' => now()->subDays(2)->toDateString()]);
    $latest = DailyBrief::factory()->for($user->workspace)->create(['brief_date' => now()->subDay()->toDateString()]);
    DailyBrief::factory()->for(Workspace::factory())->create(['brief_date' => now()->toDateString()]);

    actingAs($user)
        ->get(route('daily-briefs.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('insights/briefs')
            ->where('brief.id', $latest->id)
            ->has('history', 2));
});

it('generates a brief on demand for yesterday or today so far', function (string $period, string $expectedDate) {
    Queue::fake([GenerateDailyBrief::class]);
    $this->freezeTime();
    $user = User::factory()->agent()->create();

    actingAs($user)
        ->post(route('daily-briefs.store'), ['period' => $period])
        ->assertRedirect(route('daily-briefs.index', ['date' => now()->modify($expectedDate)->toDateString()]));

    expect(DailyBrief::sole())
        ->trigger->toBe(BriefTrigger::Manual)
        ->requested_by->toBe($user->id)
        ->brief_date->toDateString()->toBe(now()->modify($expectedDate)->toDateString());

    Queue::assertPushed(GenerateDailyBrief::class);
})->with([
    'yesterday' => ['yesterday', '-1 day'],
    'today' => ['today', '+0 days'],
]);

it('regenerates an existing brief instead of duplicating it', function () {
    Queue::fake([GenerateDailyBrief::class]);
    $user = User::factory()->create();
    DailyBrief::factory()->for($user->workspace)->create(['brief_date' => now()->subDay()->toDateString()]);

    actingAs($user)->post(route('daily-briefs.store'), ['period' => 'yesterday']);

    expect(DailyBrief::count())->toBe(1)
        ->and(DailyBrief::sole()->generation_status->value)->toBe('pending');
});

it('explains when the daily brief is switched off', function () {
    Queue::fake([GenerateDailyBrief::class]);
    $user = User::factory()->create();
    AiFeatureSetting::factory()->for($user->workspace)->disabled()->create(['feature' => AiFeature::DailyBrief]);

    actingAs($user)
        ->post(route('daily-briefs.store'), ['period' => 'yesterday'])
        ->assertInertiaFlash('toast.message', AiFailureReason::FeatureDisabled->userMessage());

    Queue::assertNothingPushed();
});

it('validates the period', function () {
    actingAs(User::factory()->create())
        ->post(route('daily-briefs.store'), ['period' => 'last-year'])
        ->assertSessionHasErrors('period');
});
