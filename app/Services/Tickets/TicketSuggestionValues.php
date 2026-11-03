<?php

namespace App\Services\Tickets;

use App\Enums\Sentiment;
use App\Enums\SuggestionField;
use App\Enums\TicketPriority;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Services\AI\Suggestions\SuggestionValueHandler;
use App\Support\Languages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Turns AI output or an agent's edit into the stored {value, label} shape,
 * and writes an accepted value onto the ticket.
 */
class TicketSuggestionValues implements SuggestionValueHandler
{
    /**
     * Normalise the triage JSON from the AI into one value per field.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{value: mixed, label: string, score?: float}> Keyed by SuggestionField value.
     */
    public function fromTriage(Ticket $ticket, array $data): array
    {
        $values = [];

        if (isset($data['category'])) {
            $category = $ticket->workspace->ticketCategories->firstWhere('name', $data['category']);

            if ($category !== null) {
                $values[SuggestionField::Category->value] = ['value' => $category->id, 'label' => $category->name];
            }
        }

        $priority = TicketPriority::tryFrom((string) ($data['priority'] ?? ''));
        if ($priority !== null) {
            $values[SuggestionField::Priority->value] = ['value' => $priority->value, 'label' => $priority->label()];
        }

        $sentiment = Sentiment::tryFrom((string) ($data['sentiment'] ?? ''));
        if ($sentiment !== null) {
            $score = is_numeric($data['sentiment_score'] ?? null) ? (float) $data['sentiment_score'] : $sentiment->defaultScore();
            $values[SuggestionField::Sentiment->value] = ['value' => $sentiment->value, 'label' => $sentiment->label(), 'score' => round(max(-1, min(1, $score)), 2)];
        }

        $languageCode = Str::lower(Str::substr(trim((string) ($data['language_code'] ?? '')), 0, 10));
        if ($languageCode !== '') {
            $languageName = Languages::nameFor($languageCode) ?? Str::limit(trim((string) ($data['language_name'] ?? $languageCode)), 40, '');
            $values[SuggestionField::Language->value] = ['value' => $languageCode, 'label' => $languageName];
        }

        $summary = Str::limit(trim((string) ($data['summary'] ?? '')), 200, '…');
        if ($summary !== '') {
            $values[SuggestionField::Summary->value] = ['value' => $summary, 'label' => $summary];
        }

        return $values;
    }

    /**
     * Validate an agent's edited value for a field.
     *
     * @return array{value: mixed, label: string, score?: float}
     *
     * @throws ValidationException
     */
    public function fromEdit(Model $ticket, SuggestionField $field, mixed $input): array
    {
        if (! $ticket instanceof Ticket) {
            throw new InvalidArgumentException('Expected a ticket.');
        }

        $invalid = fn (string $message) => ValidationException::withMessages(['value' => $message]);

        return match ($field) {
            SuggestionField::Category => (function () use ($ticket, $input, $invalid): array {
                $category = is_numeric($input) ? $ticket->workspace->ticketCategories->firstWhere('id', (int) $input) : null;

                return $category instanceof TicketCategory
                    ? ['value' => $category->id, 'label' => $category->name]
                    : throw $invalid('Choose one of your ticket categories.');
            })(),
            SuggestionField::Priority => ($priority = TicketPriority::tryFrom((string) $input))
                ? ['value' => $priority->value, 'label' => $priority->label()]
                : throw $invalid('Choose a valid priority.'),
            SuggestionField::Sentiment => ($sentiment = Sentiment::tryFrom((string) $input))
                ? ['value' => $sentiment->value, 'label' => $sentiment->label(), 'score' => $sentiment->defaultScore()]
                : throw $invalid('Choose a valid sentiment.'),
            SuggestionField::Language => ($name = Languages::nameFor((string) $input))
                ? ['value' => strtolower((string) $input), 'label' => $name]
                : throw $invalid('Choose a language from the list.'),
            SuggestionField::Summary => is_string($input) && trim($input) !== '' && mb_strlen(trim($input)) <= 200
                ? ['value' => trim($input), 'label' => trim($input)]
                : throw $invalid('Write a summary of up to 200 characters.'),
            default => throw $invalid('This suggestion cannot be edited here.'),
        };
    }

    /**
     * @param  array{value: mixed, label: string, score?: float}  $value
     */
    public function apply(Model $ticket, SuggestionField $field, array $value): void
    {
        if (! $ticket instanceof Ticket) {
            throw new InvalidArgumentException('Expected a ticket.');
        }

        match ($field) {
            SuggestionField::Category => $ticket->category_id = (int) $value['value'],
            SuggestionField::Priority => $ticket->priority = TicketPriority::from((string) $value['value']),
            SuggestionField::Sentiment => [
                $ticket->sentiment = Sentiment::from((string) $value['value']),
                $ticket->sentiment_score = (string) ($value['score'] ?? Sentiment::from((string) $value['value'])->defaultScore()),
            ],
            SuggestionField::Language => [
                $ticket->language = (string) $value['value'],
                $ticket->language_name = $value['label'],
            ],
            SuggestionField::Summary => $ticket->summary = (string) $value['value'],
            default => null,
        };

        $ticket->save();
    }
}
