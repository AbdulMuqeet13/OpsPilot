<?php

namespace App\Services\AI\Drivers;

use App\Enums\AiFailureReason;
use App\Services\AI\AiClient;
use App\Services\AI\AiException;
use Generator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared transport for HTTP-based providers: auth headers, timeouts,
 * retries with exponential backoff (honouring retry-after), error mapping
 * and Server-Sent Events parsing.
 */
abstract class HttpAiClient implements AiClient
{
    /**
     * HTTP statuses worth retrying: timeouts, rate limits, server errors and Anthropic's 529 overload.
     */
    private const array RETRYABLE_STATUSES = [408, 409, 429, 500, 502, 503, 504, 529];

    /**
     * @param  array<string, mixed>  $config  The provider's block from config/ai.php.
     */
    public function __construct(
        protected readonly array $config,
        protected readonly ?string $configuredChatModel,
        protected readonly ?string $configuredEmbeddingModel,
        protected readonly int $timeoutSeconds,
        protected readonly int $maxAttempts,
        protected readonly int $retryBaseDelayMs,
        protected readonly int $retryMaxDelayMs,
    ) {}

    /**
     * @return array<string, string>
     */
    abstract protected function authHeaders(string $apiKey): array;

    /**
     * Provider-specific 429s that will never succeed on retry (for example an exhausted quota).
     */
    protected function isPermanentRateLimit(Response $response): bool
    {
        return false;
    }

    public function chatModel(): string
    {
        if ($this->configuredChatModel === null || $this->configuredChatModel === '') {
            throw new AiException(AiFailureReason::NotConfigured, 'AI_MODEL is not set.');
        }

        return $this->configuredChatModel;
    }

    protected function embeddingModel(): string
    {
        if ($this->configuredEmbeddingModel === null || $this->configuredEmbeddingModel === '') {
            throw new AiException(AiFailureReason::NotConfigured, 'AI_EMBEDDING_MODEL is not set.');
        }

        return $this->configuredEmbeddingModel;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $extraHeaders
     *
     * @throws AiException
     */
    protected function post(string $path, array $payload, array $extraHeaders = [], bool $stream = false): Response
    {
        try {
            $response = $this->pendingRequest($extraHeaders)
                ->withOptions(['stream' => $stream])
                ->post($path, $payload);
        } catch (ConnectionException $exception) {
            throw new AiException(AiFailureReason::Unavailable, $this->providerName().' could not be reached: '.$exception->getMessage(), $exception);
        } catch (RequestException $exception) {
            throw $this->exceptionForResponse($exception->response, $exception);
        }

        if ($response->failed()) {
            throw $this->exceptionForResponse($response);
        }

        return $response;
    }

    /**
     * @param  array<string, string>  $extraHeaders
     */
    private function pendingRequest(array $extraHeaders): PendingRequest
    {
        $apiKey = $this->config['api_key'] ?? null;

        if (! is_string($apiKey) || $apiKey === '') {
            throw new AiException(AiFailureReason::NotConfigured, 'No API key is configured for '.$this->providerName().'.');
        }

        return Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeoutSeconds)
            ->connectTimeout(10)
            ->withHeaders([...$this->authHeaders($apiKey), ...$extraHeaders])
            ->retry(
                max(1, $this->maxAttempts),
                fn (int $attempt, Throwable $exception): int => $this->retryDelayMs($attempt, $exception),
                fn (Throwable $exception): bool => $this->shouldRetry($exception),
            );
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if (! $exception instanceof RequestException) {
            return false;
        }

        if ($exception->response->status() === 429 && $this->isPermanentRateLimit($exception->response)) {
            return false;
        }

        return in_array($exception->response->status(), self::RETRYABLE_STATUSES, true);
    }

    /**
     * Exponential backoff (1s, 2s, 4s… by default), unless the provider says exactly how long to wait.
     */
    private function retryDelayMs(int $attempt, Throwable $exception): int
    {
        $delay = $this->retryBaseDelayMs * (2 ** ($attempt - 1));

        if ($exception instanceof RequestException) {
            $retryAfterMs = $exception->response->header('retry-after-ms');
            $retryAfterSeconds = $exception->response->header('retry-after');

            if (is_numeric($retryAfterMs)) {
                $delay = (int) $retryAfterMs;
            } elseif (is_numeric($retryAfterSeconds)) {
                $delay = (int) ((float) $retryAfterSeconds * 1000);
            }
        }

        return min($delay, $this->retryMaxDelayMs);
    }

    private function exceptionForResponse(Response $response, ?Throwable $previous = null): AiException
    {
        $status = $response->status();
        $detail = sprintf('%s returned HTTP %d: %s', $this->providerName(), $status, Str::limit($response->body(), 500));

        $reason = match (true) {
            $status === 429 && $this->isPermanentRateLimit($response) => AiFailureReason::Rejected,
            $status === 429 => AiFailureReason::RateLimited,
            in_array($status, self::RETRYABLE_STATUSES, true) => AiFailureReason::Unavailable,
            default => AiFailureReason::Rejected,
        };

        return new AiException($reason, $detail, $previous);
    }

    /**
     * Read a text/event-stream body as it arrives.
     *
     * @return Generator<int, array{event: string|null, data: string}>
     */
    protected function serverSentEvents(Response $response): Generator
    {
        $body = $response->toPsrResponse()->getBody();
        $buffer = '';
        $event = null;
        $dataLines = [];

        while (! $body->eof() || $buffer !== '') {
            if (! $body->eof()) {
                $buffer .= $body->read(8192);
            }

            $newlinePosition = strpos($buffer, "\n");

            if ($newlinePosition === false) {
                if ($body->eof()) {
                    $newlinePosition = strlen($buffer);
                    $buffer .= "\n";
                } else {
                    continue;
                }
            }

            $line = rtrim(substr($buffer, 0, $newlinePosition), "\r");
            $buffer = substr($buffer, $newlinePosition + 1);

            if ($line === '') {
                if ($dataLines !== []) {
                    yield ['event' => $event, 'data' => implode("\n", $dataLines)];
                }

                $event = null;
                $dataLines = [];

                continue;
            }

            if (str_starts_with($line, ':')) {
                continue;
            }

            [$field, $value] = array_pad(explode(':', $line, 2), 2, '');
            $value = ltrim($value, ' ');

            if ($field === 'event') {
                $event = $value;
            } elseif ($field === 'data') {
                $dataLines[] = $value;
            }
        }

        if ($dataLines !== []) {
            yield ['event' => $event, 'data' => implode("\n", $dataLines)];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonObject(string $json, string $context): array
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new AiException(AiFailureReason::InvalidResponse, "{$context}: expected a JSON object, got: ".Str::limit($json, 200));
        }

        return $decoded;
    }
}
