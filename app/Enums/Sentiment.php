<?php

namespace App\Enums;

enum Sentiment: string
{
    case Positive = 'positive';
    case Neutral = 'neutral';
    case Negative = 'negative';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * The score used when a person picks a sentiment by hand.
     */
    public function defaultScore(): float
    {
        return match ($this) {
            self::Positive => 0.6,
            self::Neutral => 0.0,
            self::Negative => -0.6,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $sentiment): string => $sentiment->value, self::cases());
    }
}
