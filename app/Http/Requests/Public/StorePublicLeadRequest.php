<?php

namespace App\Http\Requests\Public;

use App\Support\LeadFormOptions;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePublicLeadRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'company' => ['required', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'company_size' => ['nullable', Rule::in(LeadFormOptions::companySizes())],
            'budget' => ['nullable', Rule::in(LeadFormOptions::budgets())],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
            'fax_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Bots fill in every field, including the hidden "fax_number" one.
     */
    public function isLikelySpam(): bool
    {
        return $this->filled('fax_number');
    }

    /**
     * @return array{name: string, email: string, company: string, job_title: string|null, company_size: string|null, budget: string|null, website: string|null, message: string}
     */
    public function leadInput(): array
    {
        $optional = fn (string $key): ?string => $this->filled($key) ? $this->string($key)->trim()->toString() : null;

        return [
            'name' => $this->string('name')->trim()->toString(),
            'email' => $this->string('email')->trim()->lower()->toString(),
            'company' => $this->string('company')->trim()->toString(),
            'job_title' => $optional('job_title'),
            'company_size' => $optional('company_size'),
            'budget' => $optional('budget'),
            'website' => $optional('website'),
            'message' => $this->string('message')->trim()->toString(),
        ];
    }
}
