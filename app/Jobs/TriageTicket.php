<?php

namespace App\Jobs;

use App\Actions\Ai\RecordAiSuggestions;
use App\Enums\AiFeature;
use App\Enums\SuggestionDecision;
use App\Services\AI\AiGateway;
use App\Services\AI\Data\AiCallContext;
use App\Services\AI\Prompts\TicketTriagePrompt;
use App\Services\Tickets\TicketSuggestionValues;

class TriageTicket extends TicketAiJob
{
    protected function task(): string
    {
        return 'triage';
    }

    protected function run(): void
    {
        $workspace = $this->ticket->workspace;
        $prompt = new TicketTriagePrompt($this->ticket, $workspace->ticketCategories);

        $result = app(AiGateway::class)->structured(
            new AiCallContext(AiFeature::TicketTriage, $workspace, subject: $this->ticket),
            $prompt->request(),
            $prompt->schema(),
        );

        $values = app(TicketSuggestionValues::class);

        $suggestions = app(RecordAiSuggestions::class)->handle(
            $this->ticket,
            $workspace->id,
            AiFeature::TicketTriage,
            $values->fromTriage($this->ticket, $result->data),
            $result->usageLogId,
        );

        if (! $workspace->aiSettingFor(AiFeature::TicketTriage)->auto_apply) {
            return;
        }

        foreach ($suggestions as $suggestion) {
            $values->apply($this->ticket, $suggestion->field, $suggestion->suggested_value);
            $suggestion->forceFill([
                'decision' => SuggestionDecision::AutoApplied,
                'final_value' => $suggestion->suggested_value,
                'decided_at' => now(),
            ])->save();
        }
    }
}
