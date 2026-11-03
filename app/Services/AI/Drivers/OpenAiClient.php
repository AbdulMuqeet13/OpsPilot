<?php

namespace App\Services\AI\Drivers;

use App\Enums\AiFailureReason;
use App\Services\AI\AiException;
use App\Services\AI\Data\ChatRequest;
use App\Services\AI\Data\ChatResult;
use App\Services\AI\Data\EmbeddingResult;
use App\Services\AI\Data\JsonSchema;
use App\Services\AI\Data\StructuredResult;
use App\Services\AI\Data\TokenUsage;
use Closure;
use Illuminate\Http\Client\Response;

/**
 * OpenAI via Chat Completions (POST /v1/chat/completions) and Embeddings (POST /v1/embeddings).
 */
class OpenAiClient extends HttpAiClient
{
    public function providerName(): string
    {
        return 'openai';
    }

    protected function authHeaders(string $apiKey): array
    {
        return array_filter([
            'Authorization' => 'Bearer '.$apiKey,
            'OpenAI-Organization' => $this->config['organization'] ?? null,
        ]);
    }

    /**
     * An exhausted quota also returns 429, but retrying cannot fix it.
     */
    protected function isPermanentRateLimit(Response $response): bool
    {
        return $response->json('error.code') === 'insufficient_quota';
    }

    public function chat(ChatRequest $request): ChatResult
    {
        $completion = $this->post('chat/completions', $this->payload($request))->json();

        return new ChatResult(
            text: $this->contentFrom($completion),
            usage: $this->usageFrom($completion['usage'] ?? []),
            model: (string) ($completion['model'] ?? $this->chatModel()),
        );
    }

    public function structured(ChatRequest $request, JsonSchema $schema): StructuredResult
    {
        $payload = $this->payload($request);
        $payload['response_format'] = [
            'type' => 'json_schema',
            'json_schema' => ['name' => $schema->name, 'strict' => true, 'schema' => $schema->schema],
        ];

        $completion = $this->post('chat/completions', $payload)->json();

        return new StructuredResult(
            data: $this->decodeJsonObject($this->contentFrom($completion), "OpenAI structured output ({$schema->name})"),
            usage: $this->usageFrom($completion['usage'] ?? []),
            model: (string) ($completion['model'] ?? $this->chatModel()),
        );
    }

    public function streamChat(ChatRequest $request, Closure $onTextDelta): ChatResult
    {
        $response = $this->post('chat/completions', [
            ...$this->payload($request),
            'stream' => true,
            'stream_options' => ['include_usage' => true],
        ], stream: true);

        $text = '';
        $model = $this->chatModel();
        $usage = [];
        $finishReason = null;

        foreach ($this->serverSentEvents($response) as $event) {
            if ($event['data'] === '[DONE]') {
                break;
            }

            $chunk = $this->decodeJsonObject($event['data'], 'OpenAI stream chunk');
            $model = (string) ($chunk['model'] ?? $model);
            $usage = $chunk['usage'] ?? $usage;
            $choice = $chunk['choices'][0] ?? null;

            if ($choice === null) {
                continue;
            }

            if (! empty($choice['delta']['refusal'])) {
                throw new AiException(AiFailureReason::Refused, 'The model declined the request.');
            }

            if (isset($choice['delta']['content']) && $choice['delta']['content'] !== '') {
                $text .= $choice['delta']['content'];
                $onTextDelta($choice['delta']['content']);
            }

            $finishReason = $choice['finish_reason'] ?? $finishReason;
        }

        $this->guardFinishReason($finishReason);

        return new ChatResult(text: $text, usage: $this->usageFrom($usage), model: $model);
    }

    public function supportsEmbeddings(): bool
    {
        return true;
    }

    public function embed(array $texts): EmbeddingResult
    {
        $response = $this->post('embeddings', [
            'model' => $this->embeddingModel(),
            'input' => $texts,
        ])->json();

        /** @var list<array{index: int, embedding: list<float|int>}> $rows */
        $rows = $response['data'] ?? [];
        usort($rows, fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        if (count($rows) !== count($texts)) {
            throw new AiException(AiFailureReason::InvalidResponse, 'OpenAI returned '.count($rows).' embeddings for '.count($texts).' inputs.');
        }

        $vectors = array_map(
            fn (array $row): array => array_map(floatval(...), $row['embedding']),
            $rows,
        );

        return new EmbeddingResult(
            vectors: $vectors,
            usage: new TokenUsage((int) ($response['usage']['prompt_tokens'] ?? 0), 0),
            model: (string) ($response['model'] ?? $this->embeddingModel()),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ChatRequest $request): array
    {
        return [
            'model' => $this->chatModel(),
            'max_completion_tokens' => $request->maxTokens,
            'messages' => [
                ['role' => 'system', 'content' => $request->system],
                ...$request->messages,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $completion
     */
    private function contentFrom(array $completion): string
    {
        $choice = $completion['choices'][0] ?? null;

        if ($choice === null) {
            throw new AiException(AiFailureReason::InvalidResponse, 'OpenAI returned no choices.');
        }

        if (! empty($choice['message']['refusal'])) {
            throw new AiException(AiFailureReason::Refused, 'The model declined the request: '.$choice['message']['refusal']);
        }

        $this->guardFinishReason($choice['finish_reason'] ?? null);

        return (string) ($choice['message']['content'] ?? '');
    }

    private function guardFinishReason(?string $finishReason): void
    {
        if ($finishReason === 'length') {
            throw new AiException(AiFailureReason::InvalidResponse, 'The reply hit max_completion_tokens and was cut off.');
        }

        if ($finishReason === 'content_filter') {
            throw new AiException(AiFailureReason::Refused, 'The reply was blocked by the content filter.');
        }
    }

    /**
     * @param  array<string, mixed>  $usage
     */
    private function usageFrom(array $usage): TokenUsage
    {
        return new TokenUsage(
            (int) ($usage['prompt_tokens'] ?? 0),
            (int) ($usage['completion_tokens'] ?? 0),
        );
    }
}
