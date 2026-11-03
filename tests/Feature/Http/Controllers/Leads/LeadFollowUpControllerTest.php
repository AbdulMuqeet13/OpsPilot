<?php

use App\Enums\LeadStatus;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Models\AiSuggestion;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\actingAs;

const FOLLOW_UP_BODY = "Hi Dana,\n\nThanks for telling us about the hotel. Could we book 15 minutes this week to go through room counts?\n\nMaya";

function leadWithFollowUpDraft(): array
{
    $user = User::factory()->agent()->create();
    $lead = Lead::factory()->for($user->workspace)->create();
    $draft = AiSuggestion::factory()
        ->forSubject($lead)
        ->field(SuggestionField::FollowUp, ['value' => FOLLOW_UP_BODY, 'label' => 'Your hotel project', 'subject' => 'Your hotel project'])
        ->create();

    return [$user, $lead, $draft];
}

it('records the follow-up as sent without emailing anyone', function () {
    Mail::fake();
    [$user, $lead, $draft] = leadWithFollowUpDraft();

    actingAs($user)
        ->post(route('leads.follow-up.store', $lead), ['subject' => 'Your hotel project', 'body' => FOLLOW_UP_BODY])
        ->assertRedirect();

    expect($lead->fresh())
        ->status->toBe(LeadStatus::Contacted)
        ->follow_up_subject->toBe('Your hotel project')
        ->follow_up_body->toBe(FOLLOW_UP_BODY)
        ->follow_up_sent_by->toBe($user->id)
        ->follow_up_sent_at->not->toBeNull()
        ->and($draft->fresh()->decision)->toBe(SuggestionDecision::Accepted);

    Mail::assertNothingOutgoing();
});

it('counts a personalised draft as edited', function () {
    [$user, $lead, $draft] = leadWithFollowUpDraft();

    actingAs($user)->post(route('leads.follow-up.store', $lead), [
        'subject' => 'Your hotel project',
        'body' => str_replace('this week', 'on Thursday', FOLLOW_UP_BODY),
    ]);

    expect($draft->fresh()->decision)->toBe(SuggestionDecision::Edited);
});

it('keeps a qualified lead qualified', function () {
    [$user, $lead] = leadWithFollowUpDraft();
    $lead->forceFill(['status' => LeadStatus::Qualified])->save();

    actingAs($user)->post(route('leads.follow-up.store', $lead), ['subject' => 'Hi', 'body' => 'Hello']);

    expect($lead->fresh()->status)->toBe(LeadStatus::Qualified);
});

it('requires a subject and body', function () {
    [$user, $lead] = leadWithFollowUpDraft();

    actingAs($user)
        ->post(route('leads.follow-up.store', $lead), [])
        ->assertSessionHasErrors(['subject', 'body']);
});

it('returns 404 for a lead in another workspace', function () {
    actingAs(User::factory()->create())
        ->post(route('leads.follow-up.store', Lead::factory()->create()), ['subject' => 'Hi', 'body' => 'Hello'])
        ->assertNotFound();
});

it('only lets the follow-up draft be discarded through the decision endpoint', function () {
    [$user, , $draft] = leadWithFollowUpDraft();

    actingAs($user)
        ->post(route('ai-suggestions.decision.store', $draft), ['decision' => 'accept'])
        ->assertSessionHasErrors(['decision' => 'Use the editor to send a drafted message.']);
});
