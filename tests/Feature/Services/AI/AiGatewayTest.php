<?php

use App\Enums\AiCallStatus;
use App\Enums\AiFailureReason;
use App\Enums\AiFeature;
use App\Models\AiFeatureSetting;
use App\Models\AiUsageLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AI\AiException;
use App\Services\AI\AiGateway;
use App\Services\AI\Data\AiCallContext;
use App\Services\AI\Data\ChatRequest;
use App\Services\AI\Data\JsonSchema;
use App\Services\AI\Testing\FakeAiClient;

function triageSchema(): JsonSchema
{
    return new JsonSchema('triage', ['type' => 'object', 'properties' => [], 'required' => [], 'additionalProperties' => false]);
}

it('logs a successful call with tokens, cost and the subject', function () {
    $fake = FakeAiClient::install()->queueStructured(['priority' => 'high']);
    $user = User::factory()->create();
    $subject = Workspace::factory()->create();

    $result = app(AiGateway::class)->structured(
        new AiCallContext(AiFeature::TicketTriage, $user->workspace, $user, $subject),
        ChatRequest::fromPrompt('Triage this ticket.', 'My sofa arrived damaged.'),
        triageSchema(),
    );

    $log = AiUsageLog::sole();

    expect($result->data)->toBe(['priority' => 'high'])
        ->and($result->usageLogId)->toBe($log->id)
        ->and($log->workspace_id)->toBe($user->workspace_id)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->subject->is($subject))->toBeTrue()
        ->and($log->feature)->toBe(AiFeature::TicketTriage)
        ->and($log->provider)->toBe('fake')
        ->and($log->model)->toBe(FakeAiClient::MODEL)
        ->and($log->status)->toBe(AiCallStatus::Succeeded)
        ->and($log->input_tokens)->toBe(1000)
        ->and($log->output_tokens)->toBe(200)
        // Unknown models use the fallback rate: (1000 × $10 + 200 × $50) / 1M.
        ->and((float) $log->estimated_cost_usd)->toBe(0.02)
        ->and($log->input['messages'][0]['content'])->toBe('My sofa arrived damaged.')
        ->and($log->output)->toBe(['priority' => 'high']);

    $fake->assertSentCount(1);
});

it('logs a failed call and rethrows it with a friendly message', function () {
    FakeAiClient::install()->queueFailure(AiFailureReason::Unavailable, 'HTTP 529 overloaded');
    $user = User::factory()->create();

    expect(fn () => app(AiGateway::class)->chat(
        new AiCallContext(AiFeature::DailyBrief, $user->workspace, $user),
        ChatRequest::fromPrompt('Summarise.', 'Yesterday'),
    ))->toThrow(fn (AiException $exception) => expect($exception->userMessage())
        ->toBe('The AI service is temporarily unavailable. Please try again shortly.'));

    expect(AiUsageLog::sole())
        ->status->toBe(AiCallStatus::Failed)
        ->failure_reason->toBe(AiFailureReason::Unavailable)
        ->error->toBe('HTTP 529 overloaded')
        ->output_tokens->toBe(0);
});

it('records refusals with their own status', function () {
    FakeAiClient::install()->queueFailure(AiFailureReason::Refused);
    $user = User::factory()->create();

    expect(fn () => app(AiGateway::class)->chat(new AiCallContext(AiFeature::ReplyDraft, $user->workspace), ChatRequest::fromPrompt('Draft.', 'Ticket')))
        ->toThrow(AiException::class);

    expect(AiUsageLog::sole()->status)->toBe(AiCallStatus::Refused);
});

it('appends the workspace admin\'s custom instructions to the system prompt', function () {
    $fake = FakeAiClient::install()->queueText('Draft reply');
    $workspace = Workspace::factory()->create();
    $setting = AiFeatureSetting::factory()->for($workspace)->create([
        'feature' => AiFeature::ReplyDraft,
        'custom_instructions' => 'Always sign off as The Oakline Team.',
    ]);

    app(AiGateway::class)->chat(new AiCallContext(AiFeature::ReplyDraft, $workspace), ChatRequest::fromPrompt('Draft a reply.', 'Where is my order?'));

    expect($fake->lastRequest()->system)
        ->toStartWith('Draft a reply.')
        ->toContain($setting->custom_instructions);
});

it('does not call the AI when the feature is switched off', function () {
    $fake = FakeAiClient::install();
    $workspace = Workspace::factory()->create();
    AiFeatureSetting::factory()->for($workspace)->disabled()->create(['feature' => AiFeature::LeadScoring]);

    expect(fn () => app(AiGateway::class)->chat(new AiCallContext(AiFeature::LeadScoring, $workspace), ChatRequest::fromPrompt('Score.', 'Lead')))
        ->toThrow(fn (AiException $exception) => expect($exception->reason)->toBe(AiFailureReason::FeatureDisabled));

    $fake->assertNothingSent();
    expect(AiUsageLog::count())->toBe(0);
});

it('pauses AI for everyone once the demo spend cap is reached', function () {
    config(['demo.enabled' => true, 'demo.daily_ai_spend_cap_usd' => 1.00]);
    $fake = FakeAiClient::install();
    AiUsageLog::factory()->costing(0.60)->create();
    AiUsageLog::factory()->costing(0.40)->create();
    $workspace = Workspace::factory()->create();

    expect(fn () => app(AiGateway::class)->chat(new AiCallContext(AiFeature::TicketTriage, $workspace), ChatRequest::fromPrompt('Triage.', 'Ticket')))
        ->toThrow(fn (AiException $exception) => expect($exception->reason)->toBe(AiFailureReason::SpendCapReached));

    $fake->assertNothingSent();
});

it('only counts today\'s spend toward the cap', function () {
    config(['demo.enabled' => true, 'demo.daily_ai_spend_cap_usd' => 1.00]);
    $this->freezeTime();
    FakeAiClient::install()->queueText('ok');
    AiUsageLog::factory()->costing(5.00)->create(['created_at' => now()->subDay()]);

    $result = app(AiGateway::class)->chat(new AiCallContext(AiFeature::TicketTriage, Workspace::factory()->create()), ChatRequest::fromPrompt('Triage.', 'Ticket'));

    expect($result->text)->toBe('ok');
});

it('ignores the spend cap outside demo mode', function () {
    config(['demo.enabled' => false, 'demo.daily_ai_spend_cap_usd' => 1.00]);
    FakeAiClient::install()->queueText('ok');
    AiUsageLog::factory()->costing(5.00)->create();

    $result = app(AiGateway::class)->chat(new AiCallContext(AiFeature::TicketTriage, Workspace::factory()->create()), ChatRequest::fromPrompt('Triage.', 'Ticket'));

    expect($result->text)->toBe('ok');
});

it('streams text through the callback and logs the full reply', function () {
    FakeAiClient::install()->queueText('Thanks for your patience.');
    $pieces = [];

    $result = app(AiGateway::class)->streamChat(
        new AiCallContext(AiFeature::ReplyRewrite, Workspace::factory()->create()),
        ChatRequest::fromPrompt('Rewrite.', 'thx'),
        function (string $piece) use (&$pieces): void {
            $pieces[] = $piece;
        },
    );

    expect(implode('', $pieces))->toBe('Thanks for your patience.')
        ->and($result->text)->toBe('Thanks for your patience.')
        ->and(AiUsageLog::sole()->output)->toBe(['text' => 'Thanks for your patience.']);
});

it('logs embeddings without storing the embedded text', function () {
    FakeAiClient::install()->queueEmbeddings([[0.1, 0.2, 0.3]]);

    app(AiGateway::class)->embed(new AiCallContext(AiFeature::KnowledgeSearch, Workspace::factory()->create()), ['Refunds within 30 days']);

    expect(AiUsageLog::sole())
        ->input->toBe(['texts' => 1, 'characters' => 22])
        ->output->toBe(['dimensions' => 3]);
});

it('explains that embeddings are off when no embedding provider is set', function () {
    FakeAiClient::install()->withoutEmbeddings();

    expect(app(AiGateway::class)->embeddingsEnabled())->toBeFalse()
        ->and(fn () => app(AiGateway::class)->embed(new AiCallContext(AiFeature::KnowledgeSearch, Workspace::factory()->create()), ['text']))
        ->toThrow(fn (AiException $exception) => expect($exception->reason)->toBe(AiFailureReason::NotConfigured));
});
