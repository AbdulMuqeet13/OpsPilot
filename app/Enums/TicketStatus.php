<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case Pending = 'pending';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Pending => 'Waiting on customer',
            self::Resolved => 'Resolved',
        };
    }
}
