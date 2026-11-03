<?php

namespace App\Enums;

enum TicketSource: string
{
    case Form = 'form';
    case Email = 'email';
    case Api = 'api';

    public function label(): string
    {
        return match ($this) {
            self::Form => 'Contact form',
            self::Email => 'Email',
            self::Api => 'API',
        };
    }
}
