<?php

namespace App\Enums;

enum AiCallStatus: string
{
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::Succeeded => 'Succeeded',
            self::Failed => 'Failed',
            self::Refused => 'Declined by AI',
        };
    }
}
