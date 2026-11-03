<?php

use App\Enums\AiFeature;
use App\Enums\AiTaskStatus;
use App\Enums\TicketSource;
use App\Jobs\DraftTicketReply;
use App\Jobs\TriageTicket;
use App\Models\AiFeatureSetting;
use App\Models\Ticket;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

function contactSubmission(array $overrides = []): array
{
    return [
        'name' => 'Priya Raman',
        'email' => 'Priya.Raman@example.com',
        'subject' => 'Where is my dining table?',
        'message' => 'I ordered the Fenwick table three weeks ago and it still has not arrived.',
        ...$overrides,
    ];
}

it('renders the contact form in the workspace\'s own brand', function () {
    $workspace = Workspace::factory()->create(['brand_name' => 'Harbor & Pine', 'primary_color' => '#0f766e']);

    get(route('public.tickets.create', $workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public/contact')
            ->where('brand.name', 'Harbor & Pine')
            ->where('brand.primaryColor', '#0f766e'));
});

it('returns 404 for an unknown workspace', function () {
    get('/w/no-such-company/contact')->assertNotFound();
});

it('creates a ticket with the first message and queues the AI work', function () {
    Queue::fake([TriageTicket::class, DraftTicketReply::class]);
    $workspace = Workspace::factory()->create();

    post(route('public.tickets.store', $workspace), contactSubmission())
        ->assertRedirect()
        ->assertInertiaFlash('submittedReference', '#1001');

    $ticket = Ticket::sole();

    expect($ticket->workspace_id)->toBe($workspace->id)
        ->and($ticket->number)->toBe(1001)
        ->and($ticket->source)->toBe(TicketSource::Form)
        ->and($ticket->requester_email)->toBe('priya.raman@example.com')
        ->and($ticket->triage_status)->toBe(AiTaskStatus::Pending)
        ->and($ticket->priority)->toBeNull()
        ->and($ticket->messages()->sole()->body)->toBe(contactSubmission()['message']);

    Queue::assertPushed(TriageTicket::class, fn (TriageTicket $job) => $job->ticket->is($ticket));
    Queue::assertPushed(DraftTicketReply::class, fn (DraftTicketReply $job) => $job->ticket->is($ticket));
});

it('numbers tickets consecutively per workspace', function () {
    Queue::fake([TriageTicket::class, DraftTicketReply::class]);
    $workspace = Workspace::factory()->create();
    Ticket::factory()->for($workspace)->create(['number' => 1041]);
    Ticket::factory()->for(Workspace::factory())->create(['number' => 5000]);

    post(route('public.tickets.store', $workspace), contactSubmission());

    expect($workspace->tickets()->max('number'))->toBe(1042);
});

it('skips AI work for features the workspace has switched off', function () {
    Queue::fake([TriageTicket::class, DraftTicketReply::class]);
    $workspace = Workspace::factory()->create();
    AiFeatureSetting::factory()->for($workspace)->disabled()->create(['feature' => AiFeature::ReplyDraft]);

    post(route('public.tickets.store', $workspace), contactSubmission());

    expect(Ticket::sole()->draft_status)->toBe(AiTaskStatus::Skipped);
    Queue::assertPushed(TriageTicket::class);
    Queue::assertNotPushed(DraftTicketReply::class);
});

it('silently drops submissions that fill in the honeypot', function () {
    Queue::fake([TriageTicket::class, DraftTicketReply::class]);
    $workspace = Workspace::factory()->create();

    post(route('public.tickets.store', $workspace), contactSubmission(['website' => 'http://spam.example']))
        ->assertRedirect()
        ->assertInertiaFlash('submittedReference');

    expect(Ticket::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('validates the submission', function () {
    $workspace = Workspace::factory()->create();

    post(route('public.tickets.store', $workspace), contactSubmission(['email' => 'not-an-email', 'message' => 'short']))
        ->assertSessionHasErrors([
            'email' => 'The email field must be a valid email address.',
            'message' => 'The message field must be at least 10 characters.',
        ]);
});

it('limits how often one visitor can submit', function () {
    Queue::fake([TriageTicket::class, DraftTicketReply::class]);
    $workspace = Workspace::factory()->create();

    foreach (range(1, 5) as $attempt) {
        post(route('public.tickets.store', $workspace), contactSubmission());
    }

    post(route('public.tickets.store', $workspace), contactSubmission())
        ->assertSessionHasErrors('message');

    expect(Ticket::count())->toBe(5);
});
