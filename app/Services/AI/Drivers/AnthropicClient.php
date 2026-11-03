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

/**
 * Claude via the Messages API (POST /v1/messages).
 */
class AnthropicClient extends HttpAiClient
{
    private const string REFUSAL_FALLBACK_BETA = 'server-side-fallback-2026-07-01';

    public function providerName(): string
    {
        return 'anthropic';
    }

    protected function authHeaders(string $apiKey): array
    {
        return [
            'x-api-key' => $apiKey,
            'anthropic-version' => (string) $this->config['api_version'],
        ];
    }

    public function chat(ChatRequest $request): ChatResult
    {
        $message = $this->post('messages', $this->payload($request), $this->betaHeaders())->json();

        return new ChatResult(
            text: $this->textFrom($message),
            usage: $this->usageFrom($message['usage'] ?? []),
            model: (string) ($message['model'] ?? $this->chatModel()),
        );
    }

    public function structured(ChatRequest $request, JsonSchema $schema): StructuredResult
    {
        $payload = $this->payload($request);
        $payload['output_config']['format'] = ['type' => 'json_schema', 'schema' => $schema->schema];

        $message = $this->post('messages', $payload, $this->betaHeaders())->json();

        return new StructuredResult(
            data: $this->decodeJsonObject($this->textFrom($message), "Anthropic structured output ({$schema->name})"),
            usage: $this->usageFrom($message['usage'] ?? []),
            model: (string) ($message['model'] ?? $this->chatModel()),
        );
    }

    public function streamChat(ChatRequest $request, Closure $onTextDelta): ChatResult
    {
        $response = $this->post('messages', [...$this->payload($request), 'stream' => true], $this->betaHeaders(), stream: true);

        $text = '';
        $model = $this->chatModel();
        $usage = [];
        $stopReason = null;

        foreach ($this->serverSentEvents($response) as $event) {
            $data = $this->decodeJsonObject($event['data'], 'Anthropic stream event');

            switch ($data['type'] ?? null) {
                case 'message_start':
                    $model = (string) ($data['message']['model'] ?? $model);
                    $usage = $data['message']['usage'] ?? [];
                    break;

                case 'content_block_delta':
                    if (($data['delta']['type'] ?? null) === 'text_delta') {
                        $piece = (string) $data['delta']['text'];
                        $text .= $piece;
                        $onTextDelta($piece);
                    }
                    break;

                case 'message_delta':
                    $stopReason = $data['delta']['stop_reason'] ?? $stopReason;
                    $usage = [...$usage, ...($data['usage'] ?? [])];
                    break;

                case 'error':
                    $errorType = $data['error']['type'] ?? 'unknown_error';

                    throw new AiException(
                        $errorType === 'overloaded_error' ? AiFailureReason::Unavailable : AiFailureReason::InvalidResponse,
                        'Anthropic stream error: '.json_encode($data['error'] ?? $data),
                    );
            }
        }

        $this->guardStopReason($stopReason);

        return new ChatResult(text: $text, usage: $this->usageFrom($usage), model: $model);
    }

    public function supportsEmbeddings(): bool
    {
        return false;
    }

    public function embed(array $texts): EmbeddingResult
    {
        throw new AiException(
            AiFailureReason::NotConfigured,
            'Anthropic has no embeddings API. Set AI_EMBEDDING_PROVIDER=openai, or leave it empty to disable embeddings.',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ChatRequest $request): array
    {
        $payload = [
            'model' => $this->chatModel(),
            'max_tokens' => $request->maxTokens,
            'system' => $request->system,
            'messages' => $request->messages,
        ];

        if (! empty($this->config['effort'])) {
            $payload['output_config'] = ['effort' => $this->config['effort']];
        }

        if ($this->usesRefusalFallback()) {
            $payload['fallbacks'] = 'default';
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    private function betaHeaders(): array
    {
        return $this->usesRefusalFallback() ? ['anthropic-beta' => self::REFUSAL_FALLBACK_BETA] : [];
    }

    private function usesRefusalFallback(): bool
    {
        return ($this->config['refusal_fallback'] ?? false)
            && in_array($this->chatModel(), $this->config['refusal_fallback_models'] ?? [], true);
    }

    /**
     * Join the text blocks, ignoring thinking and fallback marker blocks.
     *
     * @param  array<string, mixed>  $message
     */
    private function textFrom(array $message): string
    {
        $this->guardStopReason($message['stop_reason'] ?? null);

        $text = '';

        foreach ($message['content'] ?? [] as $block) {
            if (($block['type'] ?? null) === 'text') {
                $text .= $block['text'];
            }
        }

        return $text;
    }

    private function guardStopReason(?string $stopReason): void
    {
        if ($stopReason === 'refusal') {
            throw new AiException(AiFailureReason::Refused, 'Claude declined the request (stop_reason: refusal).');
        }

        if ($stopReason === 'max_tokens') {
            throw new AiException(AiFailureReason::InvalidResponse, 'The reply hit max_tokens and was cut off.');
        }
    }

    /**
     * When a refusal fallback ran, top-level usage only covers the final attempt; iterations cover them all.
     *
     * @param  array<string, mixed>  $usage
     */
    private function usageFrom(array $usage): TokenUsage
    {
        $attempts = isset($usage['iterations']) && is_array($usage['iterations']) && $usage['iterations'] !== []
            ? $usage['iterations']
            : [$usage];

        $inputTokens = 0;
        $outputTokens = 0;

        foreach ($attempts as $attempt) {
            $inputTokens += (int) ($attempt['input_tokens'] ?? 0)
                + (int) ($attempt['cache_creation_input_tokens'] ?? 0)
                + (int) ($attempt['cache_read_input_tokens'] ?? 0);
            $outputTokens += (int) ($attempt['output_tokens'] ?? 0);
        }

        return new TokenUsage($inputTokens, $outputTokens);
    }
}
