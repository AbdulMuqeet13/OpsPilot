<?php

namespace App\Http\Requests\Settings;

use App\Models\KnowledgeSnippet;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Validator;

class KnowledgeSnippetRequest extends FormRequest
{
    public const int MAX_SNIPPETS = 50;

    public function authorize(): Response
    {
        $snippet = $this->route('snippet');

        return $snippet instanceof KnowledgeSnippet
            ? Gate::inspect('manage', $snippet)
            : Gate::inspect('manageSettings', $this->user()->workspace);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->isMethod('post') && $this->user()->workspace->knowledgeSnippets()->count() >= self::MAX_SNIPPETS) {
                    $validator->errors()->add('title', 'You can have up to '.self::MAX_SNIPPETS.' snippets. Remove one first.');
                }
            },
        ];
    }
}
