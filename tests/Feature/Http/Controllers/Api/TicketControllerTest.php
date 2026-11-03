<?php

use App\Actions\Settings\RotateWorkspaceApiKey;
use App\Enums\TicketSource;
use App\Jobs\DraftTicketReply;
use App\Jobs\TriageTicket;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\postJson;

function apiTicketPayload(array $overrides = []): array
{
    return [
        'name' => 'Sam Lee',
        'email' => 'sam@example.com',
        'subject' => 'Wrong colour delivered',
        'message' => 'I ordered the sage armchair but received the grey one.',
        ...$overrides,
    ];
}

it('returns 401 when no API key is sent', function () {
    postJson(route('api.v1.tickets.store'), apiTicketPayload())
        ->assertUnauthorized()
        ->assertJson(['message' => 'Missing or invalid API key. Send it as "Authorization: Bearer <key>".']);
});

it('returns 401 for a revoked or wrong key', function () {
    $workspace = Workspace::factory()->create();
    $oldKey = app(RotateWorkspaceApiKey::class)->handle($workspace);
    app(RotateWorkspaceApiKey::class)->handle($workspace);

    postJson(route('api.v1.tickets.store'), apiTicketPayload(), ['Authorization' => "Bearer {$oldKey}"])
        ->assertUnauthorized();
});

it('creates a ticket in the key\'s workspace and returns 201', function () {
    Queue::fake([TriageTicket::class, DraftTicketReply::class]);
    $workspace = Workspace::factory()->create();
    $key = app(RotateWorkspaceApiKey::class)->handle($workspace);

    postJson(route('api.v1.tickets.store'), apiTicketPayload(), ['Authorization' => "Bearer {$key}"])
        ->assertCreated()
        ->assertJsonPath('data.reference', '#1001')
        ->assertJsonPath('data.status', 'open')
        ->assertJsonPath('data.ai.triage', 'pending');

    expect(Ticket::sole())
        ->workspace_id->toBe($workspace->id)
        ->source->toBe(TicketSource::Api);

    Queue::assertPushed(TriageTicket::class);
});

it('returns 422 with field errors for invalid input', function () {
    $key = app(RotateWorkspaceApiKey::class)->handle(Workspace::factory()->create());

    postJson(route('api.v1.tickets.store'), apiTicketPayload(['email' => 'nope', 'message' => '']), ['Authorization' => "Bearer {$key}"])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'message']);
});

it('stores only a hash of the key', function () {
    $workspace = Workspace::factory()->create();
    $key = app(RotateWorkspaceApiKey::class)->handle($workspace);

    expect($workspace->fresh()->api_key_hash)->toBe(hash('sha256', $key))->not->toBe($key)
        ->and($workspace->fresh()->api_key_last_four)->toBe(substr($key, -4));
});
