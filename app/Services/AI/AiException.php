<?php

namespace App\Services\AI;

use App\Enums\AiFailureReason;
use RuntimeException;
use Throwable;

/**
 * The single exception type the AI layer throws. The reason decides the
 * user-facing message and whether a retry could help; the message holds
 * technical detail for the logs and is never shown to users.
 */
class AiException extends RuntimeException
{
    public function __construct(
        public readonly AiFailureReason $reason,
        string $technicalDetail = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($technicalDetail !== '' ? $technicalDetail : $reason->value, 0, $previous);
    }

    public function userMessage(): string
    {
        return $this->reason->userMessage();
    }

    public function isRetryable(): bool
    {
        return $this->reason->isRetryable();
    }
}
