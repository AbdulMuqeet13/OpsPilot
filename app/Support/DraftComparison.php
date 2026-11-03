<?php

namespace App\Support;

use App\Enums\SuggestionDecision;

/**
 * Decides what happened to an AI draft from what the person finally sent.
 */
final class DraftComparison
{
    /**
     * At or above this similarity an edited draft counts as "edited"; below it the person effectively wrote their own.
     */
    public const float EDITED_SIMILARITY_THRESHOLD = 50.0;

    public static function decisionFor(string $draft, string $sent): SuggestionDecision
    {
        $normalisedDraft = self::normalise($draft);
        $normalisedSent = self::normalise($sent);

        if ($normalisedDraft === $normalisedSent) {
            return SuggestionDecision::Accepted;
        }

        similar_text($normalisedDraft, $normalisedSent, $percent);

        return $percent >= self::EDITED_SIMILARITY_THRESHOLD ? SuggestionDecision::Edited : SuggestionDecision::Rejected;
    }

    private static function normalise(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
