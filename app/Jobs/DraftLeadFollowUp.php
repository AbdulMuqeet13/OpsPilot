<?php

namespace App\Jobs;

use App\Actions\Ai\RecordAiSuggestions;
use App\Enums\AiFailureReason;
use App\Enums\AiFeature;
use App\Enums\SuggestionField;
use App\Models\User;
use App\Services\AI\AiException;
use App\Services\AI\AiGateway;
use App\Services\AI\Data\AiCallContext;
use App\Services\AI\Prompts\LeadFollowUpPrompt;
use App\Services\Leads\LeadSuggestionValues;

class DraftLeadFollowUp extends LeadAiJob
{
    protected function task(): string
    {
        return 'follow_up';
    }

    protected function run(): void
    {
        $workspace = $this->lead->workspace;

        // Sign off as the workspace's first admin, so the draft reads like it came from a person.
        $sender = User::query()->whereBelongsTo($workspace)->orderByRaw("case when role = 'admin' then 0 else 1 end")->orderBy('id')->first();
        $prompt = new LeadFollowUpPrompt($this->lead, $sender);

        $result = app(AiGateway::class)->structured(
            new AiCallContext(AiFeature::LeadFollowUp, $workspace, subject: $this->lead),
            $prompt->request(),
            $prompt->schema(),
        );

        $value = app(LeadSuggestionValues::class)->fromFollowUp($result->data)
            ?? throw new AiException(AiFailureReason::InvalidResponse, 'The AI returned an empty follow-up email.');

        app(RecordAiSuggestions::class)->handle(
            $this->lead,
            $workspace->id,
            AiFeature::LeadFollowUp,
            [SuggestionField::FollowUp->value => $value],
            $result->usageLogId,
        );
    }
}
