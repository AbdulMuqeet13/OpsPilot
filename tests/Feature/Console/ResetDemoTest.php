<?php

use App\Actions\Demo\ResetShowcaseWorkspace;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\artisan;

it('refuses to run outside demo mode unless forced', function () {
    config(['demo.enabled' => false]);
    $expired = Workspace::factory()->sandbox()->create(['expires_at' => now()->subHour()]);

    artisan('demo:reset')->assertFailed();

    expect($expired->fresh())->not->toBeNull();
});

it('removes expired sandboxes and their files but keeps active ones', function () {
    config(['demo.enabled' => true]);
    Storage::fake('public');
    $expired = Workspace::factory()->sandbox()->create(['expires_at' => now()->subHour()]);
    User::factory()->for($expired)->create();
    Ticket::factory()->for($expired)->create();
    Storage::disk('public')->put("workspaces/{$expired->id}/branding/logo.png", 'png');
    $active = Workspace::factory()->sandbox()->create();

    artisan('demo:reset', ['--sandboxes-only' => true])->expectsOutputToContain('Removed 1 sandbox workspaces.')->assertSuccessful();

    expect(Workspace::find($expired->id))->toBeNull()
        ->and(Ticket::count())->toBe(0)
        ->and(User::count())->toBe(0)
        ->and($active->fresh())->not->toBeNull()
        ->and(Workspace::where('slug', config('demo.showcase.slug'))->exists())->toBeFalse();

    Storage::disk('public')->assertMissing("workspaces/{$expired->id}/branding/logo.png");
});

it('can remove every sandbox', function () {
    config(['demo.enabled' => true]);
    Workspace::factory()->sandbox()->count(2)->create();

    artisan('demo:reset', ['--sandboxes-only' => true, '--all-sandboxes' => true])->assertSuccessful();

    expect(Workspace::where('is_sandbox', true)->count())->toBe(0);
});

it('restores the showcase workspace, wiping what visitors added', function () {
    config(['demo.enabled' => true]);
    Storage::fake('public');
    $showcase = app(ResetShowcaseWorkspace::class)->handle();
    $showcase->forceFill(['brand_name' => 'Hijacked Inc', 'primary_color' => '#000000', 'logo_path' => "workspaces/{$showcase->id}/branding/logo.png"])->save();
    Storage::disk('public')->put($showcase->logo_path, 'png');
    Ticket::factory()->for($showcase)->create(['subject' => 'Added by a visitor', 'number' => 5000]);
    $extraUser = User::factory()->for($showcase)->create();

    artisan('demo:reset')->expectsOutputToContain('40 tickets and 25 leads')->assertSuccessful();

    $showcase->refresh();

    expect($showcase->brand_name)->toBe('Oakline Home')
        ->and($showcase->logo_path)->toBeNull()
        ->and($showcase->tickets()->where('subject', 'Added by a visitor')->exists())->toBeFalse()
        ->and($showcase->tickets()->count())->toBe(40)
        ->and(User::find($extraUser->id))->toBeNull()
        ->and($showcase->users()->pluck('email')->sort()->values()->all())->toBe(['maya@oaklinehome.example', 'theo@oaklinehome.example']);

    Storage::disk('public')->assertMissing("workspaces/{$showcase->id}/branding/logo.png");
});

it('lets you sign in to the showcase with the configured password', function () {
    config(['demo.showcase.password' => 'presentation-2026']);

    app(ResetShowcaseWorkspace::class)->handle();

    $this->post(route('login.store'), ['email' => 'maya@oaklinehome.example', 'password' => 'presentation-2026'])
        ->assertRedirect(route('dashboard'));
});

it('is scheduled hourly for sandboxes and nightly for the showcase', function () {
    $commands = collect(app(Schedule::class)->events())->mapWithKeys(fn ($event) => [trim(str_replace(["'".PHP_BINARY."'", 'artisan'], '', (string) $event->command)) => $event->expression]);

    expect($commands->first(fn ($expression, $command) => str_ends_with($command, 'demo:reset --sandboxes-only')))->toBe('0 * * * *')
        ->and($commands->first(fn ($expression, $command) => str_ends_with($command, 'demo:reset')))->toBe('0 3 * * *');
});
