<?php

namespace App\Http\Requests\Settings;

use App\Enums\AiFeature;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAiFeatureSettingsRequest extends FormRequest
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
        $featureKeys = implode(',', array_map(fn (AiFeature $feature): string => $feature->value, AiFeature::configurable()));

        return [
            'features' => ['required', 'array:'.$featureKeys],
            'features.*.is_enabled' => ['required', 'boolean'],
            'features.*.auto_apply' => ['sometimes', 'boolean'],
            'features.*.custom_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'features.*.custom_instructions.max' => 'Keep custom instructions under 1,000 characters.',
        ];
    }

    /**
     * @return array<string, array{is_enabled: bool, auto_apply: bool, custom_instructions: string|null}>
     */
    public function settingsByFeature(): array
    {
        /** @var array<string, array<string, mixed>> $features */
        $features = $this->validated('features');

        return array_map(fn (array $values): array => [
            'is_enabled' => filter_var($values['is_enabled'], FILTER_VALIDATE_BOOLEAN),
            'auto_apply' => filter_var($values['auto_apply'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'custom_instructions' => isset($values['custom_instructions']) && trim((string) $values['custom_instructions']) !== ''
                ? trim((string) $values['custom_instructions'])
                : null,
        ], $features);
    }
}
