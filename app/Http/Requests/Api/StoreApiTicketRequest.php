<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreApiTicketRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array{name: string, email: string, subject: string, message: string}
     */
    public function ticketInput(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'email' => $this->string('email')->trim()->lower()->toString(),
            'subject' => $this->string('subject')->trim()->toString(),
            'message' => $this->string('message')->trim()->toString(),
        ];
    }
}
