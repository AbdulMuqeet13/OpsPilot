<?php

use App\Enums\MessageAuthorType;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Enums\TicketStatus;
use App\Mail\TicketReplyMail;
use App\Models\AiSuggestion;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

const DRAFT_REPLY = "Hi Priya,\n\nSorry the cushion arrived torn. We'll send a replacement cover this week, free of charge.\n\nThe Oakline Home team";

function ticketWithDraft(): array
{
    $agent = User::factory()->agent()->create();
    $ticket = Ticket::factory()->for($agent->workspace)->create(['requester_email' => 'priya@example.com']);
    $draft = AiSuggestion::factory()->forTicket($ticket)->field(SuggestionField::Reply, ['value' => DRAFT_REPLY, 'label' => DRAFT_REPLY])->create();

    return [$agent, $ticket, $draft];
}

it('sends the reply, emails the customer and waits for their answer', function () {
    Mail::fake();
    [$agent, $ticket] = ticketWithDraft();

    actingAs($agent)->post(route('tickets.replies.store', $ticket), ['body' => DRAFT_REPLY])->assertRedirect();

    $reply = $ticket->messages()->where('author_type', MessageAuthorType::Agent)->sole();

    expect($reply->user_id)->toBe($agent->id)
        ->and($reply->was_emailed)->toBeTrue()
        ->and($ticket->fresh()->status)->toBe(TicketStatus::Pending);

    Mail::assertQueued(TicketReplyMail::class, fn (TicketReplyMail $mail) => $mail->hasTo('priya@example.com') && $mail->reply->is($reply));
});

it('never emails customers in demo mode', function () {
    config(['demo.enabled' => true]);
    Mail::fake();
    [$agent, $ticket] = ticketWithDraft();

    actingAs($agent)->post(route('tickets.replies.store', $ticket), ['body' => 'Thanks!']);

    expect($ticket->messages()->where('author_type', MessageAuthorType::Agent)->sole()->was_emailed)->toBeFalse();
    Mail::assertNothingOutgoing();
});

it('resolves the ticket when asked', function () {
    Mail::fake();
    [$agent, $ticket] = ticketWithDraft();

    actingAs($agent)->post(route('tickets.replies.store', $ticket), ['body' => 'All sorted.', 'resolve' => true]);

    expect($ticket->fresh())
        ->status->toBe(TicketStatus::Resolved)
        ->resolved_at->not->toBeNull();
});

it('records what happened to the AI draft', function (string $sentBody, SuggestionDecision $expected) {
    Mail::fake();
    [$agent, $ticket, $draft] = ticketWithDraft();

    actingAs($agent)->post(route('tickets.replies.store', $ticket), ['body' => $sentBody]);

    expect($draft->fresh())
        ->decision->toBe($expected)
        ->decided_by->toBe($agent->id);
})->with([
    'sent unchanged (whitespace aside)' => [str_replace("\n\n", "\n", DRAFT_REPLY), SuggestionDecision::Accepted],
    'lightly edited' => [str_replace('this week', 'tomorrow', DRAFT_REPLY), SuggestionDecision::Edited],
    'replaced with the agent\'s own reply' => ['Please call us on the number on your invoice.', SuggestionDecision::Rejected],
]);

it('stores the reply that was actually sent as the final value', function () {
    Mail::fake();
    [$agent, $ticket, $draft] = ticketWithDraft();
    $edited = str_replace('this week', 'tomorrow', DRAFT_REPLY);

    actingAs($agent)->post(route('tickets.replies.store', $ticket), ['body' => $edited]);

    expect($draft->fresh()->final_value)->toBe(['value' => $edited, 'label' => $edited]);
});

it('requires a reply body', function () {
    [$agent, $ticket] = ticketWithDraft();

    actingAs($agent)
        ->post(route('tickets.replies.store', $ticket), ['body' => ''])
        ->assertSessionHasErrors(['body' => 'Write a reply before sending.']);
});

it('returns 404 for a ticket in another workspace', function () {
    actingAs(User::factory()->create())
        ->post(route('tickets.replies.store', Ticket::factory()->create()), ['body' => 'Hello'])
        ->assertNotFound();
});
