<?php

namespace App\Http\Requests\Tickets;

use App\Services\AI\Prompts\ReplyRewritePrompt;
use App\Support\Languages;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RewriteReplyRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('update', $this->route('ticket'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:10000'],
            'style' => ['required', Rule::in(ReplyRewritePrompt::STYLES)],
            'language' => ['required_if:style,translate', 'nullable', Rule::in(array_keys(Languages::common()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['text.required' => 'Write something to rewrite first.'];
    }
}
