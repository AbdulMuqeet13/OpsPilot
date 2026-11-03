<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageSettings', $this->user()->workspace);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'brand_name' => ['required', 'string', 'max:60'],
            'primary_color' => ['required', 'string', 'hex_color', 'size:7'],
            'support_email' => ['nullable', 'email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:'.config('opspilot.logo.max_kilobytes'), 'dimensions:max_width=2000,max_height=2000'],
            'remove_logo' => ['boolean'],
        ];
    }

    /**
     * @return array{brand_name: string, primary_color: string, support_email: string|null}
     */
    public function brandingAttributes(): array
    {
        return [
            'brand_name' => $this->string('brand_name')->trim()->toString(),
            'primary_color' => $this->string('primary_color')->toString(),
            'support_email' => $this->filled('support_email') ? $this->string('support_email')->trim()->toString() : null,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.size' => 'Use a 6-digit hex color, for example #4f46e5.',
            'primary_color.hex_color' => 'Use a 6-digit hex color, for example #4f46e5.',
        ];
    }
}
