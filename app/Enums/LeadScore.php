<?php

namespace App\Enums;

enum LeadScore: string
{
    case Hot = 'hot';
    case Warm = 'warm';
    case Cold = 'cold';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $score): string => $score->value, self::cases());
    }
}
