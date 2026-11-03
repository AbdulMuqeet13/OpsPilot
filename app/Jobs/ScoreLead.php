<?php

namespace App\Jobs;

use App\Actions\Ai\RecordAiSuggestions;
use App\Enums\AiFailureReason;
use App\Enums\AiFeature;
use App\Enums\SuggestionField;
use App\Services\AI\AiException;
use App\Services\AI\AiGateway;
use App\Services\AI\Data\AiCallContext;
use App\Services\AI\Prompts\LeadScoringPrompt;
use App\Services\Leads\LeadSuggestionValues;

class ScoreLead extends LeadAiJob
{
    protected function task(): string
    {
        return 'scoring';
    }

    protected function run(): void
    {
        $workspace = $this->lead->workspace;
        $prompt = new LeadScoringPrompt($this->lead);

        $result = app(AiGateway::class)->structured(
            new AiCallContext(AiFeature::LeadScoring, $workspace, subject: $this->lead),
            $prompt->request(),
            $prompt->schema(),
        );

        $value = app(LeadSuggestionValues::class)->fromScore($result->data)
            ?? throw new AiException(AiFailureReason::InvalidResponse, 'The AI returned no valid score.');

        app(RecordAiSuggestions::class)->handle(
            $this->lead,
            $workspace->id,
            AiFeature::LeadScoring,
            [SuggestionField::LeadScore->value => $value],
            $result->usageLogId,
        );
    }
}
