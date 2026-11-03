<?php

use App\Enums\AiFailureReason;
use App\Services\AI\AiClient;
use App\Services\AI\AiException;
use App\Services\AI\AiManager;
use App\Services\AI\Data\ChatRequest;
use App\Services\AI\Data\JsonSchema;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

const ANTHROPIC_MESSAGES_URL = 'https://api.anthropic.com/v1/messages';

function anthropicClient(array $overrides = []): AiClient
{
    config([
        'ai.provider' => 'anthropic',
        'ai.model' => 'claude-opus-5-5',
        'ai.embedding_provider' => null,
        'ai.max_attempts' => 3,
        'ai.retry_base_delay_ms' => 1000,
        'ai.providers.anthropic.api_key' => 'test-key',
        'ai.providers.anthropic.effort' => 'low',
        'ai.providers.anthropic.refusal_fallback' => true,
        ...$overrides,
    ]);

    $manager = app(AiManager::class);
    $manager->forgetDrivers();

    return $manager->chatClient();
}

function anthropicMessage(array $overrides = []): array
{
    return [
        'id' => 'msg_01',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-5-5',
        'content' => [
            ['type' => 'thinking', 'thinking' => '', 'signature' => 'sig'],
            ['type' => 'text', 'text' => 'Hello from Claude.'],
        ],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 120, 'output_tokens' => 30],
        ...$overrides,
    ];
}

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
});

it('sends a Messages API request and returns the text and usage', function () {
    Http::fake([ANTHROPIC_MESSAGES_URL => Http::response(anthropicMessage())]);

    $result = anthropicClient()->chat(ChatRequest::fromPrompt('Be brief.', 'Hi', maxTokens: 500));

    expect($result->text)->toBe('Hello from Claude.')
        ->and($result->usage->inputTokens)->toBe(120)
        ->and($result->usage->outputTokens)->toBe(30)
        ->and($result->model)->toBe('claude-opus-5-5');

    Http::assertSent(fn (Request $request) => $request->url() === ANTHROPIC_MESSAGES_URL
        && $request->hasHeader('x-api-key', 'test-key')
        && $request->hasHeader('anthropic-version', '2026-10-21')
        && $request->hasHeader('anthropic-beta', 'server-side-fallback-2026-07-01')
        && $request['model'] === 'claude-opus-5-5'
        && $request['max_tokens'] === 500
        && $request['system'] === 'Be brief.'
        && $request['messages'] === [['role' => 'user', 'content' => 'Hi']]
        && $request['output_config'] === ['effort' => 'low']
        && $request['fallbacks'] === 'default'
        && ! isset($request['temperature']));
});

it('omits refusal fallbacks for models that do not support them', function () {
    Http::fake([ANTHROPIC_MESSAGES_URL => Http::response(anthropicMessage(['model' => 'claude-haiku-5-5']))]);

    anthropicClient(['ai.model' => 'claude-haiku-5-5'])->chat(ChatRequest::fromPrompt('Be brief.', 'Hi'));

    Http::assertSent(fn (Request $request) => ! isset($request['fallbacks']) && ! $request->hasHeader('anthropic-beta'));
});

it('omits output_config entirely when no effort is configured', function () {
    Http::fake([ANTHROPIC_MESSAGES_URL => Http::response(anthropicMessage())]);

    anthropicClient(['ai.providers.anthropic.effort' => null])->chat(ChatRequest::fromPrompt('Be brief.', 'Hi'));

    Http::assertSent(fn (Request $request) => ! array_key_exists('output_config', $request->data()));
});

it('requests and decodes structured JSON output', function () {
    Http::fake([ANTHROPIC_MESSAGES_URL => Http::response(anthropicMessage([
        'content' => [['type' => 'text', 'text' => '{"priority":"high","summary":"Sofa arrived damaged"}']],
    ]))]);

    $schema = new JsonSchema('triage', [
        'type' => 'object',
        'properties' => ['priority' => ['type' => 'string'], 'summary' => ['type' => 'string']],
        'required' => ['priority', 'summary'],
        'additionalProperties' => false,
    ]);

    $result = anthropicClient()->structured(ChatRequest::fromPrompt('Triage.', 'My sofa is broken'), $schema);

    expect($result->data)->toBe(['priority' => 'high', 'summary' => 'Sofa arrived damaged']);

    Http::assertSent(fn (Request $request) => $request['output_config'] === [
        'effort' => 'low',
        'format' => ['type' => 'json_schema', 'schema' => $schema->schema],
    ]);
});

it('counts every attempt when a refusal fallback ran', function () {
    Http::fake([ANTHROPIC_MESSAGES_URL => Http::response(anthropicMessage([
        'model' => 'claude-opus-4-8',
        'usage' => [
            'input_tokens' => 100,
            'output_tokens' => 40,
            'iterations' => [
                ['type' => 'message', 'input_tokens' => 100, 'output_tokens' => 0],
                ['type' => 'fallback_message', 'input_tokens' => 100, 'output_tokens' => 40],
            ],
        ],
    ]))]);

    $result = anthropicClient()->chat(ChatRequest::fromPrompt('Be brief.', 'Hi'));

    expect($result->usage->inputTokens)->toBe(200)
        ->and($result->usage->outputTokens)->toBe(40)
        ->and($result->model)->toBe('claude-opus-4-8');
});

it('throws a refusal error when Claude declines', function () {
    Http::fake([ANTHROPIC_MESSAGES_URL => Http::response(anthropicMessage(['content' => [], 'stop_reason' => 'refusal']))]);

    expect(fn () => anthropicClient()->chat(ChatRequest::fromPrompt('Be brief.', 'Hi')))
        ->toThrow(fn (AiException $exception) => expect($exception->reason)->toBe(AiFailureReason::Refused));
});

it('treats a reply cut off at max_tokens as invalid', function () {
    Http::fake([ANTHROPIC_MESSAGES_URL => Http::response(anthropicMessage(['stop_reason' => 'max_tokens']))]);

    expect(fn () => anthropicClient()->chat(ChatRequest::fromPrompt('Be brief.', 'Hi')))
        ->toThrow(fn (AiException $exception) => expect($exception->reason)->toBe(AiFailureReason::InvalidResponse));
});

it('retries a rate limit after the retry-after delay and then succeeds', function () {
    Http::fake([ANTHROPIC_MESSAGES_URL => Http::sequence()
        ->push(['type' => 'error', 'error' => ['type' => 'rate_limit_error', 'message' => 'Slow down']], 429, ['retry-after' => '3'])
        ->push(anthropicMessage())]);

    $result = anthropicClient()->chat(ChatRequest::fromPrompt('Be brief.', 'Hi'));

    expect($result->text)->toBe('Hello from Claude.');
    Http::assertSentCount(2);
    Sleep::assertSlept(fn ($duration) => (int) $duration->totalMilliseconds === 3000, times: 1);
});

it('backs off exponentially on overloads and gives up after the last attempt', function () {
    Http::fake([ANTHROPIC_MESSAGES_URL => Http::response(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']], 529)]);

    expect(fn () => anthropicClient()->chat(ChatRequest::fromPrompt('Be brief.', 'Hi')))
        ->toThrow(fn (AiException $exception) => expect($exception->reason)->toBe(AiFailureReason::Unavailable)
            ->and($exception->isRetryable())->toBeTrue());

    Http::assertSentCount(3);
    Sleep::assertSequence([Sleep::for(1000)->milliseconds(), Sleep::for(2000)->milliseconds()]);
});

it('does not retry a bad request', function () {
    Http::fake([ANTHROPIC_MESSAGES_URL => Http::response(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'Bad']], 400)]);

    expect(fn () => anthropicClient()->chat(ChatRequest::fromPrompt('Be brief.', 'Hi')))
        ->toThrow(fn (AiException $exception) => expect($exception->reason)->toBe(AiFailureReason::Rejected));

    Http::assertSentCount(1);
    Sleep::assertNeverSlept();
});

it('refuses to call the API without a key', function () {
    expect(fn () => anthropicClient(['ai.providers.anthropic.api_key' => null])->chat(ChatRequest::fromPrompt('Be brief.', 'Hi')))
        ->toThrow(fn (AiException $exception) => expect($exception->reason)->toBe(AiFailureReason::NotConfigured));

    Http::assertNothingSent();
});

it('streams text deltas and ignores thinking blocks', function () {
    Http::fake([ANTHROPIC_MESSAGES_URL => Http::response(file_get_contents(base_path('tests/Fixtures/ai/anthropic-stream.txt')), 200, ['Content-Type' => 'text/event-stream'])]);

    $pieces = [];
    $result = anthropicClient()->streamChat(ChatRequest::fromPrompt('Be brief.', 'Hi'), function (string $piece) use (&$pieces): void {
        $pieces[] = $piece;
    });

    expect($pieces)->toBe(['Connected and ', 'ready to help.'])
        ->and($result->text)->toBe('Connected and ready to help.')
        ->and($result->usage->inputTokens)->toBe(42)
        ->and($result->usage->outputTokens)->toBe(17);

    Http::assertSent(fn (Request $request) => $request['stream'] === true);
});

it('surfaces an error event in the middle of a stream', function () {
    $body = "event: message_start\ndata: {\"type\":\"message_start\",\"message\":{\"model\":\"claude-opus-5-5\",\"usage\":{\"input_tokens\":5}}}\n\n"
        ."event: error\ndata: {\"type\":\"error\",\"error\":{\"type\":\"overloaded_error\",\"message\":\"Overloaded\"}}\n\n";
    Http::fake([ANTHROPIC_MESSAGES_URL => Http::response($body, 200, ['Content-Type' => 'text/event-stream'])]);

    expect(fn () => anthropicClient()->streamChat(ChatRequest::fromPrompt('Be brief.', 'Hi'), fn () => null))
        ->toThrow(fn (AiException $exception) => expect($exception->reason)->toBe(AiFailureReason::Unavailable));
});

it('reports that it cannot create embeddings', function () {
    $client = anthropicClient();

    expect($client->supportsEmbeddings())->toBeFalse()
        ->and(fn () => $client->embed(['text']))->toThrow(AiException::class);
});
