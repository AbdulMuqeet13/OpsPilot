<?php

namespace App\Enums;

enum AiFeature: string
{
    case TicketTriage = 'ticket_triage';
    case ReplyDraft = 'reply_draft';
    case ReplyRewrite = 'reply_rewrite';
    case LeadScoring = 'lead_scoring';
    case LeadFollowUp = 'lead_follow_up';
    case DailyBrief = 'daily_brief';
    case KnowledgeSearch = 'knowledge_search';
    case ConnectionTest = 'connection_test';

    public function label(): string
    {
        return match ($this) {
            self::TicketTriage => 'Ticket triage',
            self::ReplyDraft => 'Reply drafts',
            self::ReplyRewrite => 'Rewrite toolbar',
            self::LeadScoring => 'Lead scoring',
            self::LeadFollowUp => 'Follow-up emails',
            self::DailyBrief => 'Daily brief',
            self::KnowledgeSearch => 'Knowledge matching',
            self::ConnectionTest => 'Connection test',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TicketTriage => 'Suggests a category, priority, sentiment, language and one-line summary for every new ticket.',
            self::ReplyDraft => 'Drafts a first reply to each ticket using your knowledge snippets.',
            self::ReplyRewrite => 'Makes a reply friendlier, shorter or more formal, or translates it.',
            self::LeadScoring => 'Scores each lead hot, warm or cold against your ideal customer, with a short reason.',
            self::LeadFollowUp => 'Drafts a personalised first follow-up email for each lead.',
            self::DailyBrief => "Summarises yesterday's tickets and leads, recurring themes and urgent items.",
            self::KnowledgeSearch => 'Finds the knowledge snippets most relevant to a ticket.',
            self::ConnectionTest => 'Checks that the AI provider is reachable.',
        };
    }

    /**
     * Whether admins can switch the feature on or off and give it custom instructions.
     */
    public function isConfigurable(): bool
    {
        return ! in_array($this, [self::KnowledgeSearch, self::ConnectionTest], true);
    }

    /**
     * Whether admins may let this feature's suggestions apply without review.
     */
    public function supportsAutoApply(): bool
    {
        return $this === self::TicketTriage;
    }

    /**
     * @return list<self>
     */
    public static function configurable(): array
    {
        return array_values(array_filter(self::cases(), fn (self $feature): bool => $feature->isConfigurable()));
    }
}
