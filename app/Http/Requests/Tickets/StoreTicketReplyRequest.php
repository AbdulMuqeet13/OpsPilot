<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreTicketReplyRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:10000'],
            'resolve' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['body.required' => 'Write a reply before sending.'];
    }
}
