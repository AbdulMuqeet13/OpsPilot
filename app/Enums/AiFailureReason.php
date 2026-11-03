<?php

namespace App\Enums;

enum AiFailureReason: string
{
    case NotConfigured = 'not_configured';
    case Rejected = 'rejected';
    case RateLimited = 'rate_limited';
    case Unavailable = 'unavailable';
    case Refused = 'refused';
    case InvalidResponse = 'invalid_response';
    case FeatureDisabled = 'feature_disabled';
    case SpendCapReached = 'spend_cap_reached';
    case UsageLimitReached = 'usage_limit_reached';

    /**
     * The message shown to people using the app. Never includes provider details.
     */
    public function userMessage(): string
    {
        return match ($this) {
            self::NotConfigured => "AI isn't set up yet. An admin needs to add an AI provider API key.",
            self::Rejected => 'The AI provider rejected the request. An admin should check the AI configuration.',
            self::RateLimited => 'The AI service is busy right now. Please try again in a minute.',
            self::Unavailable => 'The AI service is temporarily unavailable. Please try again shortly.',
            self::Refused => 'The AI declined to help with this one. You can handle it manually.',
            self::InvalidResponse => 'The AI returned an incomplete answer. Please try again.',
            self::FeatureDisabled => 'This AI feature is turned off for your workspace.',
            self::SpendCapReached => "Today's AI budget for the demo has been used up. AI features will be back tomorrow, and everything else still works.",
            self::UsageLimitReached => "You've used AI a lot in a short time. Please wait a moment and try again.",
        };
    }

    /**
     * Whether the same request might succeed if it is tried again later.
     */
    public function isRetryable(): bool
    {
        return in_array($this, [self::RateLimited, self::Unavailable, self::InvalidResponse], true);
    }

    /**
     * Whether the app's own rules stopped the call before it was attempted (these are not logged as AI calls).
     */
    public function isPolicyBlock(): bool
    {
        return in_array($this, [self::FeatureDisabled, self::SpendCapReached, self::UsageLimitReached], true);
    }
}
