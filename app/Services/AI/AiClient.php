<?php

namespace App\Services\AI;

use App\Services\AI\Data\ChatRequest;
use App\Services\AI\Data\ChatResult;
use App\Services\AI\Data\EmbeddingResult;
use App\Services\AI\Data\JsonSchema;
use App\Services\AI\Data\StructuredResult;
use Closure;

/**
 * A provider driver. Implementations make one logical request each (with
 * their own retries) and throw AiException for every failure. Logging,
 * budgets and feature switches live in AiGateway, not here.
 */
interface AiClient
{
    public function providerName(): string;

    /**
     * The model used for chat, structured and streaming calls.
     */
    public function chatModel(): string;

    public function chat(ChatRequest $request): ChatResult;

    /**
     * Stream the reply, calling $onTextDelta with each new piece of text.
     *
     * @param  Closure(string): void  $onTextDelta
     */
    public function streamChat(ChatRequest $request, Closure $onTextDelta): ChatResult;

    /**
     * Get a reply that is guaranteed to match the given JSON Schema.
     */
    public function structured(ChatRequest $request, JsonSchema $schema): StructuredResult;

    public function supportsEmbeddings(): bool;

    /**
     * @param  list<string>  $texts
     */
    public function embed(array $texts): EmbeddingResult;
}
