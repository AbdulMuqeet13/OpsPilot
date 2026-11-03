<?php

namespace App\Services\AI\Testing;

use App\Enums\AiFailureReason;
use App\Services\AI\AiClient;
use App\Services\AI\AiException;
use App\Services\AI\AiManager;
use App\Services\AI\Data\ChatRequest;
use App\Services\AI\Data\ChatResult;
use App\Services\AI\Data\EmbeddingResult;
use App\Services\AI\Data\JsonSchema;
use App\Services\AI\Data\StructuredResult;
use App\Services\AI\Data\TokenUsage;
use Closure;
use LogicException;
use PHPUnit\Framework\Assert;

/**
 * Stand-in provider for tests and offline demos. Queue the replies you
 * expect, run the code under test, then assert on the recorded requests.
 */
class FakeAiClient implements AiClient
{
    public const string MODEL = 'fake-model';

    /**
     * @var list<string|array<string, mixed>|AiException>
     */
    private array $queue = [];

    /**
     * @var list<array{method: string, request: ChatRequest|null, schema: JsonSchema|null, texts: list<string>|null}>
     */
    private array $recorded = [];

    public function __construct(private TokenUsage $usagePerCall = new TokenUsage(1000, 200)) {}

    /**
     * Swap the real providers for this fake for the rest of the test.
     */
    public static function install(): self
    {
        $fake = new self;
        $manager = app(AiManager::class);
        $manager->forgetDrivers();
        $manager->useClient('fake', $fake);

        config([
            'ai.provider' => 'fake',
            'ai.model' => self::MODEL,
            'ai.embedding_provider' => 'fake',
            'ai.embedding_model' => 'fake-embedding-model',
        ]);

        return $fake;
    }

    public function withoutEmbeddings(): self
    {
        config(['ai.embedding_provider' => null]);

        return $this;
    }

    public function queueText(string ...$texts): self
    {
        array_push($this->queue, ...$texts);

        return $this;
    }

    /**
     * @param  array<string, mixed>  ...$payloads
     */
    public function queueStructured(array ...$payloads): self
    {
        array_push($this->queue, ...$payloads);

        return $this;
    }

    /**
     * @param  list<list<float>>  $vectors
     */
    public function queueEmbeddings(array $vectors): self
    {
        $this->queue[] = ['__embeddings' => $vectors];

        return $this;
    }

    public function queueFailure(AiFailureReason $reason, string $detail = 'Simulated failure'): self
    {
        $this->queue[] = new AiException($reason, $detail);

        return $this;
    }

    public function providerName(): string
    {
        return 'fake';
    }

    public function chatModel(): string
    {
        return self::MODEL;
    }

    public function chat(ChatRequest $request): ChatResult
    {
        $this->record('chat', request: $request);

        return new ChatResult($this->nextText(), $this->usagePerCall, self::MODEL);
    }

    public function streamChat(ChatRequest $request, Closure $onTextDelta): ChatResult
    {
        $this->record('stream', request: $request);
        $text = $this->nextText();

        foreach (preg_split('/(?<=\s)/', $text) ?: [] as $piece) {
            $onTextDelta($piece);
        }

        return new ChatResult($text, $this->usagePerCall, self::MODEL);
    }

    public function structured(ChatRequest $request, JsonSchema $schema): StructuredResult
    {
        $this->record('structured', request: $request, schema: $schema);
        $next = $this->next();

        if (! is_array($next) || isset($next['__embeddings'])) {
            throw new LogicException('The next queued fake AI reply is not a structured payload.');
        }

        return new StructuredResult($next, $this->usagePerCall, self::MODEL);
    }

    public function supportsEmbeddings(): bool
    {
        return true;
    }

    public function embed(array $texts): EmbeddingResult
    {
        $this->record('embed', texts: $texts);
        $next = $this->next();

        if (! is_array($next) || ! isset($next['__embeddings'])) {
            throw new LogicException('The next queued fake AI reply is not an embeddings response.');
        }

        return new EmbeddingResult($next['__embeddings'], new TokenUsage($this->usagePerCall->inputTokens, 0), 'fake-embedding-model');
    }

    /**
     * @return list<array{method: string, request: ChatRequest|null, schema: JsonSchema|null, texts: list<string>|null}>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }

    public function lastRequest(): ?ChatRequest
    {
        return $this->recorded === [] ? null : $this->recorded[array_key_last($this->recorded)]['request'];
    }

    public function assertSentCount(int $expected): void
    {
        Assert::assertCount($expected, $this->recorded, 'Unexpected number of AI calls.');
    }

    public function assertNothingSent(): void
    {
        $this->assertSentCount(0);
    }

    /**
     * @param  Closure(ChatRequest, JsonSchema|null): bool  $matches
     */
    public function assertSent(Closure $matches): void
    {
        $found = array_filter(
            $this->recorded,
            fn (array $call): bool => $call['request'] !== null && $matches($call['request'], $call['schema']),
        );

        Assert::assertNotEmpty($found, 'No matching AI call was made.');
    }

    /**
     * @param  list<string>|null  $texts
     */
    private function record(string $method, ?ChatRequest $request = null, ?JsonSchema $schema = null, ?array $texts = null): void
    {
        $this->recorded[] = ['method' => $method, 'request' => $request, 'schema' => $schema, 'texts' => $texts];
    }

    private function nextText(): string
    {
        $next = $this->next();

        if (! is_string($next)) {
            throw new LogicException('The next queued fake AI reply is not text.');
        }

        return $next;
    }

    /**
     * @return string|array<string, mixed>
     */
    private function next(): string|array
    {
        if ($this->queue === []) {
            throw new LogicException('An AI call was made but no fake reply was queued.');
        }

        $next = array_shift($this->queue);

        if ($next instanceof AiException) {
            throw $next;
        }

        return $next;
    }
}
