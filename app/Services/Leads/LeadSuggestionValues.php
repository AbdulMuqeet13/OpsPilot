<?php

namespace App\Services\Leads;

use App\Enums\LeadScore;
use App\Enums\SuggestionField;
use App\Models\Lead;
use App\Services\AI\Suggestions\SuggestionValueHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class LeadSuggestionValues implements SuggestionValueHandler
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{value: string, label: string, reason: string, signals: list<string>}|null
     */
    public function fromScore(array $data): ?array
    {
        $score = LeadScore::tryFrom((string) ($data['score'] ?? ''));

        if ($score === null) {
            return null;
        }

        $signals = array_values(array_filter(array_map(
            fn (mixed $signal): string => Str::limit(trim((string) $signal), 80, '…'),
            array_slice((array) ($data['signals'] ?? []), 0, 4),
        )));

        return [
            'value' => $score->value,
            'label' => $score->label(),
            'reason' => Str::limit(trim((string) ($data['reason'] ?? '')), 500, '…'),
            'signals' => $signals,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{value: string, label: string, subject: string}|null
     */
    public function fromFollowUp(array $data): ?array
    {
        $subject = Str::limit(trim((string) ($data['subject'] ?? '')), 200, '');
        $body = trim((string) ($data['body'] ?? ''));

        return $subject !== '' && $body !== '' ? ['value' => $body, 'label' => $subject, 'subject' => $subject] : null;
    }

    public function fromEdit(Model $subject, SuggestionField $field, mixed $input): array
    {
        if ($field !== SuggestionField::LeadScore) {
            throw ValidationException::withMessages(['value' => 'This suggestion cannot be edited here.']);
        }

        $score = LeadScore::tryFrom((string) $input) ?? throw ValidationException::withMessages(['value' => 'Choose hot, warm or cold.']);

        return ['value' => $score->value, 'label' => $score->label()];
    }

    public function apply(Model $subject, SuggestionField $field, array $value): void
    {
        if (! $subject instanceof Lead || $field !== SuggestionField::LeadScore) {
            throw new InvalidArgumentException('Only lead scores can be applied to a lead.');
        }

        $subject->score = LeadScore::from((string) $value['value']);

        if (isset($value['reason'])) {
            $subject->score_reason = $value['reason'];
        }

        $subject->save();
    }
}
