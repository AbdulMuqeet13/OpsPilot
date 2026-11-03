<?php

namespace App\Actions\Tickets;

use App\Actions\Ai\RecordAiSuggestions;
use App\Enums\AiFeature;
use App\Enums\SuggestionField;
use App\Models\AiSuggestion;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AI\AiGateway;
use App\Services\AI\Data\AiCallContext;
use App\Services\AI\Data\ChatResult;
use App\Services\AI\Prompts\ReplyDraftPrompt;
use App\Services\Knowledge\KnowledgeRetriever;
use Closure;

class GenerateReplyDraft
{
    public function __construct(
        private AiGateway $gateway,
        private KnowledgeRetriever $knowledge,
        private RecordAiSuggestions $recordSuggestions,
    ) {}

    /**
     * Draft a reply grounded in the workspace's knowledge snippets and store it as a pending suggestion.
     *
     * @param  (Closure(string): void)|null  $onTextDelta  Pass to stream the draft as it's written.
     * @return array{0: ChatResult, 1: AiSuggestion}
     */
    public function handle(Ticket $ticket, ?User $user = null, ?Closure $onTextDelta = null): array
    {
        $prompt = new ReplyDraftPrompt($ticket, $this->knowledge->relevantTo($ticket));
        $context = new AiCallContext(AiFeature::ReplyDraft, $ticket->workspace, $user, $ticket);

        /** @var ChatResult $result */
        $result = $onTextDelta === null
            ? $this->gateway->chat($context, $prompt->request())
            : $this->gateway->streamChat($context, $prompt->request(), $onTextDelta);

        $reply = trim($result->text);

        $suggestion = $this->recordSuggestions->handle(
            $ticket,
            $ticket->workspace_id,
            AiFeature::ReplyDraft,
            [SuggestionField::Reply->value => ['value' => $reply, 'label' => $reply, 'sources' => $prompt->sourceTitles()]],
            $result->usageLogId,
        )->sole();

        return [$result, $suggestion];
    }
}
