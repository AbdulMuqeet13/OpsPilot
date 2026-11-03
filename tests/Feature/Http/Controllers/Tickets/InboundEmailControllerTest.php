<?php

use App\Enums\TicketSource;
use App\Jobs\DraftTicketReply;
use App\Jobs\TriageTicket;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

it('turns a pasted email into a ticket and opens it', function () {
    Queue::fake([TriageTicket::class, DraftTicketReply::class]);
    $agent = User::factory()->agent()->create();

    $response = actingAs($agent)->post(route('inbound-email.store'), [
        'raw_email' => "From: Priya Raman <priya@example.com>\nSubject: Late table\n\nStill waiting for my table.",
    ]);

    $ticket = Ticket::sole();
    $response->assertRedirect(route('tickets.show', $ticket));

    expect($ticket->workspace_id)->toBe($agent->workspace_id)
        ->and($ticket->source)->toBe(TicketSource::Email)
        ->and($ticket->requester_email)->toBe('priya@example.com')
        ->and($ticket->subject)->toBe('Late table');
});

it('explains what is wrong with an unreadable email', function () {
    actingAs(User::factory()->create())
        ->post(route('inbound-email.store'), ['raw_email' => 'just some text with no headers'])
        ->assertSessionHasErrors(['raw_email' => 'We couldn\'t find a valid "From:" address in the email headers.']);

    expect(Ticket::count())->toBe(0);
});
