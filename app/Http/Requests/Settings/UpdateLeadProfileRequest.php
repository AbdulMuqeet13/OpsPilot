<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageSettings', $this->user()->workspace);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ideal_customer_profile' => ['nullable', 'string', 'max:2000'],
            'offer_summary' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
