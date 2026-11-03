<?php

use App\Enums\AiFeature;
use App\Enums\BriefTrigger;
use App\Jobs\GenerateDailyBrief;
use App\Models\AiFeatureSetting;
use App\Models\DailyBrief;
use App\Models\Workspace;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\artisan;

it('queues yesterday\'s brief for every active workspace', function () {
    Queue::fake([GenerateDailyBrief::class]);
    $this->freezeTime();
    $regular = Workspace::factory()->create();
    $liveSandbox = Workspace::factory()->sandbox()->create();
    Workspace::factory()->sandbox()->create(['expires_at' => now()->subHour()]);

    artisan('briefs:generate')->expectsOutputToContain('Queued 2 daily briefs')->assertSuccessful();

    expect(DailyBrief::pluck('workspace_id')->sort()->values()->all())->toBe([$regular->id, $liveSandbox->id])
        ->and(DailyBrief::first()->brief_date->toDateString())->toBe(now()->subDay()->toDateString())
        ->and(DailyBrief::first()->trigger)->toBe(BriefTrigger::Scheduled);

    Queue::assertPushed(GenerateDailyBrief::class, 2);
});

it('skips workspaces with the brief switched off or already written', function () {
    Queue::fake([GenerateDailyBrief::class]);
    $off = Workspace::factory()->create();
    AiFeatureSetting::factory()->for($off)->disabled()->create(['feature' => AiFeature::DailyBrief]);
    $done = Workspace::factory()->create();
    DailyBrief::factory()->for($done)->create(['brief_date' => now()->subDay()->toDateString()]);

    artisan('briefs:generate')->expectsOutputToContain('Queued 0 daily briefs')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('accepts a specific date', function () {
    Queue::fake([GenerateDailyBrief::class]);
    Workspace::factory()->create();

    artisan('briefs:generate', ['--date' => '2026-10-01'])->assertSuccessful();

    expect(DailyBrief::sole()->brief_date->toDateString())->toBe('2026-10-01');
});

it('is scheduled every morning', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'briefs:generate'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 6 * * *');
});
