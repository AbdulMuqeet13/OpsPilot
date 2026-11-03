<?php

namespace App\Enums;

enum SuggestionField: string
{
    case Category = 'category';
    case Priority = 'priority';
    case Sentiment = 'sentiment';
    case Language = 'language';
    case Summary = 'summary';
    case Reply = 'reply';
    case LeadScore = 'lead_score';
    case FollowUp = 'follow_up';

    public function label(): string
    {
        return match ($this) {
            self::Category => 'Category',
            self::Priority => 'Priority',
            self::Sentiment => 'Sentiment',
            self::Language => 'Language',
            self::Summary => 'Summary',
            self::Reply => 'Reply draft',
            self::LeadScore => 'Lead score',
            self::FollowUp => 'Follow-up email',
        };
    }

    /**
     * @return list<self>
     */
    public static function ticketTriageFields(): array
    {
        return [self::Category, self::Priority, self::Sentiment, self::Language, self::Summary];
    }
}
