<?php

namespace App\Http\Controllers\Tickets;

use App\Actions\Ai\DecideAiSuggestion;
use App\Enums\SuggestionDecision;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\DecideAiSuggestionRequest;
use App\Models\AiSuggestion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AiSuggestionDecisionController extends Controller
{
    public function store(DecideAiSuggestionRequest $request, AiSuggestion $suggestion, DecideAiSuggestion $decideAiSuggestion): RedirectResponse
    {
        $decision = $request->decision();

        $decideAiSuggestion->handle($suggestion, $request->user(), $decision, $request->input('value'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => match ($decision) {
                SuggestionDecision::Accepted => "{$suggestion->field->label()} accepted.",
                SuggestionDecision::Edited => "{$suggestion->field->label()} updated with your edit.",
                default => "{$suggestion->field->label()} suggestion dismissed.",
            },
        ]);

        return back();
    }
}
