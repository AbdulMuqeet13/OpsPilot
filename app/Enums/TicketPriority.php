<?php

namespace App\Enums;

enum TicketPriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Higher is more urgent; used for sorting.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Normal => 2,
            self::High => 3,
            self::Urgent => 4,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $priority): string => $priority->value, self::cases());
    }
}
