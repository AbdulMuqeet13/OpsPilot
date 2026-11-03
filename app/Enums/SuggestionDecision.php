<?php

namespace App\Enums;

enum SuggestionDecision: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Edited = 'edited';
    case Rejected = 'rejected';
    case AutoApplied = 'auto_applied';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting review',
            self::Accepted => 'Accepted',
            self::Edited => 'Edited',
            self::Rejected => 'Rejected',
            self::AutoApplied => 'Auto-applied',
            self::Superseded => 'Replaced by a newer suggestion',
        };
    }

    /**
     * Decisions a person made, which count toward the acceptance rate.
     *
     * @return list<self>
     */
    public static function humanDecisions(): array
    {
        return [self::Accepted, self::Edited, self::Rejected];
    }
}
