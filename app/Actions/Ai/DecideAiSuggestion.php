<?php

namespace App\Actions\Ai;

use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Models\AiSuggestion;
use App\Models\User;
use App\Services\AI\Suggestions\SuggestionValueHandlers;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideAiSuggestion
{
    /**
     * Drafts (reply, follow-up) are accepted or edited by sending them, so here they can only be discarded.
     */
    private const array DRAFT_FIELDS = [SuggestionField::Reply, SuggestionField::FollowUp];

    public function __construct(private SuggestionValueHandlers $handlers) {}

    /**
     * Record a person's accept / edit / reject decision and apply the result to the record.
     *
     * @throws ValidationException
     */
    public function handle(AiSuggestion $suggestion, User $user, SuggestionDecision $decision, mixed $editedValue = null): AiSuggestion
    {
        return DB::transaction(function () use ($suggestion, $user, $decision, $editedValue): AiSuggestion {
            /** @var AiSuggestion $suggestion */
            $suggestion = AiSuggestion::query()->lockForUpdate()->findOrFail($suggestion->id);

            if (! $suggestion->isPending()) {
                throw ValidationException::withMessages(['decision' => 'Someone already reviewed this suggestion.']);
            }

            if (in_array($suggestion->field, self::DRAFT_FIELDS, true) && $decision !== SuggestionDecision::Rejected) {
                throw ValidationException::withMessages(['decision' => 'Use the editor to send a drafted message.']);
            }

            $subject = $suggestion->subject;
            $handler = $this->handlers->for($subject);

            $finalValue = match ($decision) {
                SuggestionDecision::Accepted => $suggestion->suggested_value,
                SuggestionDecision::Edited => [
                    ...$handler->fromEdit($subject, $suggestion->field, $editedValue),
                    ...$this->keptContext($suggestion),
                ],
                SuggestionDecision::Rejected => null,
                default => throw ValidationException::withMessages(['decision' => 'Choose accept, edit or reject.']),
            };

            if ($finalValue !== null) {
                $handler->apply($subject, $suggestion->field, $finalValue);
            }

            $suggestion->forceFill([
                'decision' => $decision,
                'final_value' => $finalValue,
                'decided_by' => $user->id,
                'decided_at' => now(),
            ])->save();

            return $suggestion;
        });
    }

    /**
     * When a person overrides a lead score, keep the AI's reasoning visible but mark it as overridden.
     *
     * @return array{reason?: string}
     */
    private function keptContext(AiSuggestion $suggestion): array
    {
        if ($suggestion->field !== SuggestionField::LeadScore || empty($suggestion->suggested_value['reason'])) {
            return [];
        }

        return ['reason' => "AI suggested {$suggestion->suggested_value['label']}: {$suggestion->suggested_value['reason']}"];
    }
}
