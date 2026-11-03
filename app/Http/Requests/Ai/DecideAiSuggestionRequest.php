<?php

namespace App\Http\Requests\Ai;

use App\Enums\SuggestionDecision;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DecideAiSuggestionRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('decide', $this->route('suggestion'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['accept', 'edit', 'reject'])],
            'value' => ['required_if:decision,edit'],
        ];
    }

    public function decision(): SuggestionDecision
    {
        return match ($this->string('decision')->toString()) {
            'accept' => SuggestionDecision::Accepted,
            'edit' => SuggestionDecision::Edited,
            default => SuggestionDecision::Rejected,
        };
    }
}
