<?php

namespace App\Enums;

enum LeadSource: string
{
    case Form = 'form';
    case Csv = 'csv';

    public function label(): string
    {
        return match ($this) {
            self::Form => 'Web form',
            self::Csv => 'CSV import',
        };
    }
}
