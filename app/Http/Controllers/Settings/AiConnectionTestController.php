<?php

namespace App\Http\Controllers\Settings;

use App\Enums\AiFeature;
use App\Http\Controllers\Controller;
use App\Http\Responses\AiTextStream;
use App\Services\AI\AiException;
use App\Services\AI\AiGateway;
use App\Services\AI\Data\AiCallContext;
use App\Services\AI\Data\ChatRequest;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AiConnectionTestController extends Controller
{
    /**
     * Stream a short reply so an admin can see the provider, model and streaming all work.
     */
    public function store(Request $request, AiGateway $gateway): StreamedResponse|JsonResponse
    {
        $workspace = $request->user()->workspace;

        Gate::authorize('manageSettings', $workspace);

        $context = new AiCallContext(AiFeature::ConnectionTest, $workspace, $request->user());

        try {
            $gateway->ensureAvailable($workspace, $context->feature);
        } catch (AiException $exception) {
            return response()->json(['message' => $exception->userMessage()], 503);
        }

        $chatRequest = ChatRequest::fromPrompt(
            system: 'You are the AI assistant inside a support and sales dashboard. Reply in two short, friendly sentences.',
            userMessage: "Confirm you're connected and say one thing you can help {$workspace->brand_name}'s support team with.",
            maxTokens: 1000,
        );

        return AiTextStream::response(
            fn (Closure $onTextDelta) => $gateway->streamChat($context, $chatRequest, $onTextDelta),
        );
    }
}
