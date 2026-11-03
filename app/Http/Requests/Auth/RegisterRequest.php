<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    /**
     * @return array{name: string, email: string, password: string, company_name: string}
     */
    public function registrationDetails(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'email' => $this->string('email')->trim()->toString(),
            'password' => $this->string('password')->toString(),
            'company_name' => $this->string('company_name')->trim()->toString(),
        ];
    }
}
