<?php

namespace App\Enums;

enum BriefTrigger: string
{
    case Scheduled = 'scheduled';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Generated automatically',
            self::Manual => 'Generated on request',
        };
    }
}
