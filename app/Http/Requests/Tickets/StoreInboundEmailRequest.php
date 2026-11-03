<?php

namespace App\Http\Requests\Tickets;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreInboundEmailRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'raw_email' => ['required', 'string', 'max:50000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['raw_email.required' => 'Paste the full email, including its headers.'];
    }
}
