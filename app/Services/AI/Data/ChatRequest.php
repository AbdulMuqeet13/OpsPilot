<?php

namespace App\Services\AI\Data;

final readonly class ChatRequest
{
    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $messages
     */
    public function __construct(
        public string $system,
        public array $messages,
        public int $maxTokens = 4000,
    ) {}

    public static function fromPrompt(string $system, string $userMessage, int $maxTokens = 4000): self
    {
        return new self($system, [['role' => 'user', 'content' => $userMessage]], $maxTokens);
    }

    /**
     * Append workspace-specific guidance from the AI feature settings.
     */
    public function withAdditionalInstructions(?string $instructions): self
    {
        if ($instructions === null || trim($instructions) === '') {
            return $this;
        }

        return new self(
            system: $this->system."\n\nAdditional instructions from this workspace's admin (follow them unless they conflict with the rules above):\n".trim($instructions),
            messages: $this->messages,
            maxTokens: $this->maxTokens,
        );
    }

    /**
     * @return array{system: string, messages: list<array{role: 'user'|'assistant', content: string}>}
     */
    public function toLogArray(): array
    {
        return ['system' => $this->system, 'messages' => $this->messages];
    }
}
