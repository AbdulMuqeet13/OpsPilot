<?php

use App\Actions\Demo\SeedOaklineSampleData;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Models\AiSuggestion;
use App\Models\AiUsageLog;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\AiSpendGuard;

function seededWorkspace(): Workspace
{
    $workspace = Workspace::factory()->create();
    User::factory()->for($workspace)->create(['name' => 'Maya Brooks']);
    User::factory()->agent()->for($workspace)->create(['name' => 'Theo Ramirez']);

    app(SeedOaklineSampleData::class)->handle($workspace);

    return $workspace->fresh();
}

it('creates the full Oakline Home dataset', function () {
    $workspace = seededWorkspace();

    expect($workspace->brand_name)->toBe('Oakline Home')
        ->and($workspace->ideal_customer_profile)->not->toBeEmpty()
        ->and($workspace->ticketCategories()->count())->toBe(5)
        ->and($workspace->knowledgeSnippets()->count())->toBe(9)
        ->and($workspace->tickets()->count())->toBe(40)
        ->and($workspace->tickets()->orderBy('number')->pluck('number')->all())->toBe(range(1001, 1040))
        ->and($workspace->leads()->count())->toBe(25)
        ->and($workspace->dailyBriefs()->count())->toBe(3);
});

it('includes a realistic mix of human decisions', function () {
    $workspace = seededWorkspace();
    $decisions = $workspace->aiSuggestions()->pluck('decision');

    expect($decisions)->toContain(SuggestionDecision::Accepted, SuggestionDecision::Edited, SuggestionDecision::Rejected, SuggestionDecision::Pending);
});

it('leaves unreviewed tickets with pending suggestions and their fields unset', function () {
    $workspace = seededWorkspace();
    $unreviewed = $workspace->tickets()->where('subject', 'Where is my dining table? Order OH-48213')->sole();

    expect($unreviewed)
        ->priority->toBeNull()
        ->category_id->toBeNull()
        ->summary->toBeNull()
        ->and($unreviewed->aiSuggestions()->pending()->whereIn('field', SuggestionField::ticketTriageFields())->count())->toBe(5)
        ->and($unreviewed->aiSuggestions()->pending()->where('field', SuggestionField::Reply)->count())->toBe(1);
});

it('records answered tickets with the reply that was sent', function () {
    $workspace = seededWorkspace();
    $answered = $workspace->tickets()->where('subject', 'Delivery slot never came')->sole();

    expect($answered->messages()->count())->toBe(2)
        ->and($answered->priority->value)->toBe('urgent')
        ->and(AiSuggestion::whereMorphedTo('subject', $answered)->where('field', SuggestionField::Priority)->sole()->decision)->toBe(SuggestionDecision::Edited)
        ->and($answered->resolved_at)->not->toBeNull();
});

it('never costs anything or counts toward the demo AI budget', function () {
    config(['demo.enabled' => true, 'demo.daily_ai_spend_cap_usd' => 0.01]);

    seededWorkspace();

    expect((float) AiUsageLog::sum('estimated_cost_usd'))->toBe(0.0)
        ->and(AiUsageLog::distinct()->pluck('provider')->all())->toBe(['sample-data'])
        ->and(app(AiSpendGuard::class)->capReached())->toBeFalse();
});

it('never puts timestamps in the future', function () {
    $this->freezeTime();
    $workspace = seededWorkspace();

    expect($workspace->tickets()->where('created_at', '>', now())->count())->toBe(0)
        ->and($workspace->aiSuggestions()->where('decided_at', '>', now())->count())->toBe(0)
        ->and(Ticket::query()->join('ticket_messages', 'tickets.id', '=', 'ticket_messages.ticket_id')->where('ticket_messages.sent_at', '>', now())->count())->toBe(0);
});

it('uses only fictional, reserved email domains', function () {
    $workspace = seededWorkspace();
    $emails = $workspace->tickets()->pluck('requester_email')->merge($workspace->leads()->pluck('email'));

    expect($emails->reject(fn (string $email): bool => (bool) preg_match('/@([a-z0-9-]+\.)*(example|example\.com|example\.net|example\.org|example\.es|example\.de|example\.fr)$/', $email))->all())->toBe([]);
});
