<?php

namespace App\Services\AI\Suggestions;

use App\Enums\SuggestionField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Knows how to validate a person's edit for a suggestion and write the final value onto its record.
 */
interface SuggestionValueHandler
{
    /**
     * @return array{value: mixed, label: string, score?: float, reason?: string, signals?: list<string>, sources?: list<string>, subject?: string}
     *
     * @throws ValidationException
     */
    public function fromEdit(Model $subject, SuggestionField $field, mixed $input): array;

    /**
     * @param  array{value: mixed, label: string, score?: float, reason?: string, signals?: list<string>, sources?: list<string>, subject?: string}  $value
     */
    public function apply(Model $subject, SuggestionField $field, array $value): void;
}
