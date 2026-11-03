<?php

namespace App\Services\AI;

use App\Enums\AiFailureReason;
use App\Services\AI\Drivers\AnthropicClient;
use App\Services\AI\Drivers\OpenAiClient;
use Illuminate\Support\Manager;
use InvalidArgumentException;

/**
 * Resolves the configured provider drivers. Add a provider by writing an
 * AiClient driver and a create{Name}Driver() method, or call extend().
 */
class AiManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('ai.provider');
    }

    public function chatClient(): AiClient
    {
        return $this->resolveClient($this->getDefaultDriver());
    }

    /**
     * The client used for embeddings, or null when embeddings are switched off.
     */
    public function embeddingClient(): ?AiClient
    {
        $provider = $this->config->get('ai.embedding_provider');

        return is_string($provider) && $provider !== '' ? $this->resolveClient($provider) : null;
    }

    /**
     * Register a ready-made client under a provider name (used by FakeAiClient).
     */
    public function useClient(string $provider, AiClient $client): void
    {
        $this->drivers[$provider] = $client;
    }

    protected function createAnthropicDriver(): AnthropicClient
    {
        return new AnthropicClient(...$this->driverArguments('anthropic'));
    }

    protected function createOpenaiDriver(): OpenAiClient
    {
        return new OpenAiClient(...$this->driverArguments('openai'));
    }

    private function resolveClient(string $provider): AiClient
    {
        try {
            $client = $this->driver($provider);
        } catch (InvalidArgumentException $exception) {
            throw new AiException(AiFailureReason::NotConfigured, "Unknown AI provider [{$provider}]. Use \"anthropic\" or \"openai\".", $exception);
        }

        if (! $client instanceof AiClient) {
            throw new AiException(AiFailureReason::NotConfigured, "The [{$provider}] driver does not implement AiClient.");
        }

        return $client;
    }

    /**
     * @return array{config: array<string, mixed>, configuredChatModel: string|null, configuredEmbeddingModel: string|null, timeoutSeconds: int, maxAttempts: int, retryBaseDelayMs: int, retryMaxDelayMs: int}
     */
    private function driverArguments(string $provider): array
    {
        return [
            'config' => (array) $this->config->get("ai.providers.{$provider}", []),
            'configuredChatModel' => $this->config->get('ai.provider') === $provider ? $this->config->get('ai.model') : null,
            'configuredEmbeddingModel' => $this->config->get('ai.embedding_provider') === $provider ? $this->config->get('ai.embedding_model') : null,
            'timeoutSeconds' => (int) $this->config->get('ai.timeout_seconds'),
            'maxAttempts' => (int) $this->config->get('ai.max_attempts'),
            'retryBaseDelayMs' => (int) $this->config->get('ai.retry_base_delay_ms'),
            'retryMaxDelayMs' => (int) $this->config->get('ai.retry_max_delay_ms'),
        ];
    }
}
