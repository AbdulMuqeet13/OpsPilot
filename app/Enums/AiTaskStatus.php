<?php

namespace App\Enums;

enum AiTaskStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function isInProgress(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }
}
