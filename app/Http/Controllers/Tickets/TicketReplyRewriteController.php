<?php

namespace App\Http\Controllers\Tickets;

use App\Enums\AiFeature;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tickets\RewriteReplyRequest;
use App\Http\Responses\AiTextStream;
use App\Models\Ticket;
use App\Services\AI\AiException;
use App\Services\AI\AiGateway;
use App\Services\AI\Data\AiCallContext;
use App\Services\AI\Prompts\ReplyRewritePrompt;
use App\Support\Languages;
use Closure;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketReplyRewriteController extends Controller
{
    /**
     * Rewrite the agent's reply (friendlier, shorter, more formal, or translated) and stream it back.
     */
    public function store(RewriteReplyRequest $request, Ticket $ticket, AiGateway $gateway): StreamedResponse|JsonResponse
    {
        $context = new AiCallContext(AiFeature::ReplyRewrite, $ticket->workspace, $request->user(), $ticket);

        try {
            $gateway->ensureAvailable($ticket->workspace, $context->feature);
        } catch (AiException $exception) {
            return response()->json(['message' => $exception->userMessage()], 503);
        }

        $prompt = new ReplyRewritePrompt(
            draft: $request->string('text')->toString(),
            style: $request->string('style')->toString(),
            targetLanguage: Languages::nameFor($request->string('language')->toString()),
            brandName: $ticket->workspace->brand_name,
        );

        return AiTextStream::response(
            fn (Closure $onTextDelta) => $gateway->streamChat($context, $prompt->request(), $onTextDelta),
        );
    }
}
