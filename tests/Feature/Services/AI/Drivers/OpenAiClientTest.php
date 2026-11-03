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

const OPENAI_CHAT_URL = 'https://api.openai.com/v1/chat/completions';
const OPENAI_EMBEDDINGS_URL = 'https://api.openai.com/v1/embeddings';

function openAiClient(array $overrides = []): AiClient
{
    config([
        'ai.provider' => 'openai',
        'ai.model' => 'gpt-4.1-mini',
        'ai.embedding_provider' => 'openai',
        'ai.embedding_model' => 'text-embedding-3-small',
        'ai.max_attempts' => 3,
        'ai.providers.openai.api_key' => 'sk-test',
        ...$overrides,
    ]);

    $manager = app(AiManager::class);
    $manager->forgetDrivers();

    return $manager->chatClient();
}

function openAiCompletion(array $message = ['role' => 'assistant', 'content' => 'Hello there.'], string $finishReason = 'stop'): array
{
    return [
        'id' => 'chatcmpl-1',
        'object' => 'chat.completion',
        'model' => 'gpt-4.1-mini-2025-04-14',
        'choices' => [['index' => 0, 'message' => ['refusal' => null, ...$message], 'finish_reason' => $finishReason]],
        'usage' => ['prompt_tokens' => 80, 'completion_tokens' => 12, 'total_tokens' => 92],
    ];
}

beforeEach(function () {
    Http::preventStrayRequests();
    Sleep::fake();
});

it('sends a chat completion with the system prompt first', function () {
    Http::fake([OPENAI_CHAT_URL => Http::response(openAiCompletion())]);

    $result = openAiClient()->chat(ChatRequest::fromPrompt('Be brief.', 'Hi', maxTokens: 300));

    expect($result->text)->toBe('Hello there.')
        ->and($result->usage->inputTokens)->toBe(80)
        ->and($result->usage->outputTokens)->toBe(12)
        ->and($result->model)->toBe('gpt-4.1-mini-2025-04-14');

    Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer sk-test')
        && $request['model'] === 'gpt-4.1-mini'
        && $request['max_completion_tokens'] === 300
        && $request['messages'] === [
            ['role' => 'system', 'content' => 'Be brief.'],
            ['role' => 'user', 'content' => 'Hi'],
        ]);
});

it('requests a strict JSON schema and decodes the reply', function () {
    Http::fake([OPENAI_CHAT_URL => Http::response(openAiCompletion(['role' => 'assistant', 'content' => '{"score":"hot"}']))]);
    $schema = new JsonSchema('lead_score', [
        'type' => 'object',
        'properties' => ['score' => ['type' => 'string']],
        'required' => ['score'],
        'additionalProperties' => false,
    ]);

    $result = openAiClient()->structured(ChatRequest::fromPrompt('Score.', 'Lead'), $schema);

    expect($result->data)->toBe(['score' => 'hot']);
    Http::assertSent(fn (Request $request) => $request['response_format'] === [
        'type' => 'json_schema',
        'json_schema' => ['name' => 'lead_score', 'strict' => true, 'schema' => $schema->schema],
    ]);
});

it('throws a refusal error when the model refuses', function () {
    Http::fake([OPENAI_CHAT_URL => Http::response(openAiCompletion(['role' => 'assistant', 'content' => null, 'refusal' => 'I cannot help with that.']))]);

    expect(fn () => openAiClient()->chat(ChatRequest::fromPrompt('Be brief.', 'Hi')))
        ->toThrow(fn (AiException $exception) => expect($exception->reason)->toBe(AiFailureReason::Refused));
});

it('does not retry when the quota is exhausted', function () {
    Http::fake([OPENAI_CHAT_URL => Http::response(['error' => ['code' => 'insufficient_quota', 'message' => 'Quota exceeded']], 429)]);

    expect(fn () => openAiClient()->chat(ChatRequest::fromPrompt('Be brief.', 'Hi')))
        ->toThrow(fn (AiException $exception) => expect($exception->reason)->toBe(AiFailureReason::Rejected));

    Http::assertSentCount(1);
});

it('retries a temporary rate limit honouring retry-after-ms', function () {
    Http::fake([OPENAI_CHAT_URL => Http::sequence()
        ->push(['error' => ['code' => 'rate_limit_exceeded']], 429, ['retry-after-ms' => '250'])
        ->push(openAiCompletion())]);

    expect(openAiClient()->chat(ChatRequest::fromPrompt('Be brief.', 'Hi'))->text)->toBe('Hello there.');

    Sleep::assertSlept(fn ($duration) => (int) $duration->totalMilliseconds === 250, times: 1);
});

it('streams content deltas and reads usage from the final chunk', function () {
    Http::fake([OPENAI_CHAT_URL => Http::response(file_get_contents(base_path('tests/Fixtures/ai/openai-stream.txt')), 200, ['Content-Type' => 'text/event-stream'])]);

    $pieces = [];
    $result = openAiClient()->streamChat(ChatRequest::fromPrompt('Be brief.', 'Hi'), function (string $piece) use (&$pieces): void {
        $pieces[] = $piece;
    });

    expect($pieces)->toBe(['Hello ', 'there.'])
        ->and($result->text)->toBe('Hello there.')
        ->and($result->usage->inputTokens)->toBe(30)
        ->and($result->usage->outputTokens)->toBe(4);

    Http::assertSent(fn (Request $request) => $request['stream'] === true && $request['stream_options'] === ['include_usage' => true]);
});

it('creates embeddings in input order', function () {
    Http::fake([OPENAI_EMBEDDINGS_URL => Http::response([
        'object' => 'list',
        'model' => 'text-embedding-3-small',
        'data' => [
            ['object' => 'embedding', 'index' => 1, 'embedding' => [0.3, 0.4]],
            ['object' => 'embedding', 'index' => 0, 'embedding' => [0.1, 0.2]],
        ],
        'usage' => ['prompt_tokens' => 9, 'total_tokens' => 9],
    ])]);

    $manager = app(AiManager::class);
    openAiClient();
    $result = $manager->embeddingClient()->embed(['Refund policy', 'Delivery times']);

    expect($result->vectors)->toBe([[0.1, 0.2], [0.3, 0.4]])
        ->and($result->usage->inputTokens)->toBe(9);

    Http::assertSent(fn (Request $request) => $request['model'] === 'text-embedding-3-small'
        && $request['input'] === ['Refund policy', 'Delivery times']);
});
