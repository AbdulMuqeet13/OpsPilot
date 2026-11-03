<?php

namespace App\Http\Controllers\Tickets;

use App\Actions\Tickets\GenerateReplyDraft;
use App\Enums\AiFeature;
use App\Http\Controllers\Controller;
use App\Http\Resources\AiSuggestionResource;
use App\Http\Responses\AiTextStream;
use App\Models\Ticket;
use App\Services\AI\AiException;
use App\Services\AI\AiGateway;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketReplyDraftController extends Controller
{
    /**
     * Regenerate the AI reply draft, streaming it into the editor as it's written.
     */
    public function store(Request $request, Ticket $ticket, AiGateway $gateway, GenerateReplyDraft $generateReplyDraft): StreamedResponse|JsonResponse
    {
        Gate::authorize('update', $ticket);

        try {
            $gateway->ensureAvailable($ticket->workspace, AiFeature::ReplyDraft);
        } catch (AiException $exception) {
            return response()->json(['message' => $exception->userMessage()], 503);
        }

        return AiTextStream::response(function (Closure $onTextDelta) use ($ticket, $request, $generateReplyDraft): array {
            [$result, $suggestion] = $generateReplyDraft->handle($ticket, $request->user(), $onTextDelta);

            return [$result, ['suggestion' => (new AiSuggestionResource($suggestion))->resolve($request)]];
        });
    }
}
