<?php

namespace App\Actions\Demo;

use App\Enums\AiCallStatus;
use App\Enums\AiFeature;
use App\Enums\AiTaskStatus;
use App\Enums\BriefTrigger;
use App\Enums\CategoryColor;
use App\Enums\LeadScore;
use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use App\Enums\MessageAuthorType;
use App\Enums\Sentiment;
use App\Enums\SuggestionDecision;
use App\Enums\SuggestionField;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Models\AiSuggestion;
use App\Models\AiUsageLog;
use App\Models\DailyBrief;
use App\Models\Lead;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Insights\DailyBriefData;
use App\Support\Languages;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fills a workspace with Oakline Home's sample tickets, leads, AI suggestions,
 * human decisions and daily briefs, so the demo looks alive before any real
 * AI call. Sample AI calls are logged with the "sample-data" provider and a
 * cost of $0, so they never count toward the demo's AI budget.
 */
class SeedOaklineSampleData
{
    public const string SAMPLE_PROVIDER = 'sample-data';

    public const string SAMPLE_MODEL = 'pre-generated';

    private CarbonImmutable $now;

    public function __construct(private DailyBriefData $briefData) {}

    public function handle(Workspace $workspace): void
    {
        $this->now = CarbonImmutable::now();

        DB::transaction(function () use ($workspace): void {
            $workspace->forceFill([
                'brand_name' => 'Oakline Home',
                'primary_color' => OaklineHomeSampleData::BRAND_COLOR,
                'support_email' => OaklineHomeSampleData::SUPPORT_EMAIL,
                'ideal_customer_profile' => OaklineHomeSampleData::IDEAL_CUSTOMER,
                'offer_summary' => OaklineHomeSampleData::OFFER,
            ])->save();

            $people = $this->people($workspace);
            $categories = $this->seedCategories($workspace);

            foreach (OaklineHomeSampleData::snippets() as $snippet) {
                $workspace->knowledgeSnippets()->create($snippet);
            }

            $this->seedTickets($workspace, $categories, $people);
            $this->seedLeads($workspace, $people);

            foreach ([3, 2, 1] as $daysAgo) {
                $this->seedBrief($workspace, $this->now->subDays($daysAgo)->startOfDay(), $categories);
            }
        });
    }

    /**
     * @return array{admin: User|null, agent: User|null}
     */
    private function people(Workspace $workspace): array
    {
        $users = $workspace->users()->orderBy('id')->get();

        return [
            'admin' => $users->first(fn (User $user): bool => $user->isAdmin()),
            'agent' => $users->first(fn (User $user): bool => ! $user->isAdmin()) ?? $users->first(),
        ];
    }

    /**
     * @return array<string, TicketCategory>
     */
    private function seedCategories(Workspace $workspace): array
    {
        $categories = [];
        $position = 0;

        foreach (OaklineHomeSampleData::categories() as $key => $category) {
            $categories[$key] = $workspace->ticketCategories()->create([
                'name' => $category['name'],
                'description' => $category['description'],
                'color' => CategoryColor::from($category['color']),
                'position' => $position++,
            ]);
        }

        return $categories;
    }

    /**
     * @param  array<string, TicketCategory>  $categories
     * @param  array{admin: User|null, agent: User|null}  $people
     */
    private function seedTickets(Workspace $workspace, array $categories, array $people): void
    {
        $rows = collect(OaklineHomeSampleData::tickets())
            ->map(fn (array $row, int $index): array => [...$row, 'created_at' => $this->moment((int) $row['days'], $index)])
            ->sortBy('created_at')
            ->values();

        $number = 1000;

        foreach ($rows as $index => $row) {
            $this->seedTicket($workspace, ++$number, $row, $categories, $people, $index);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, TicketCategory>  $categories
     * @param  array{admin: User|null, agent: User|null}  $people
     */
    private function seedTicket(Workspace $workspace, int $number, array $row, array $categories, array $people, int $index): void
    {
        /** @var CarbonImmutable $createdAt */
        $createdAt = $row['created_at'];
        $agent = $index % 3 === 0 ? ($people['admin'] ?? $people['agent']) : ($people['agent'] ?? $people['admin']);
        $status = TicketStatus::from($row['status']);
        $hasReply = $status !== TicketStatus::Open;
        $languageCode = $row['lang'] ?? 'en';
        $firstName = Str::before($row['name'], ' ');
        $category = $categories[$row['cat']];

        $ticket = new Ticket;
        $ticket->workspace_id = $workspace->id;
        $ticket->number = $number;
        $ticket->source = TicketSource::from($row['source'] ?? 'form');
        $ticket->status = $status;
        $ticket->requester_name = $row['name'];
        $ticket->requester_email = $row['email'];
        $ticket->subject = $row['subject'];
        $ticket->body = $row['body'];
        $ticket->triage_status = AiTaskStatus::Completed;
        $ticket->draft_status = AiTaskStatus::Completed;
        $ticket->assigned_to = $hasReply ? $agent?->id : null;
        $ticket->created_at = $createdAt;
        $ticket->updated_at = $createdAt;
        $ticket->last_activity_at = $createdAt;
        $ticket->save();

        $this->message($ticket, MessageAuthorType::Customer, null, $row['body'], $createdAt);

        $aiValues = [
            SuggestionField::Category->value => ['value' => $category->id, 'label' => $category->name],
            SuggestionField::Priority->value => ['value' => $row['priority'], 'label' => TicketPriority::from($row['priority'])->label()],
            SuggestionField::Sentiment->value => ['value' => $row['sentiment'], 'label' => Sentiment::from($row['sentiment'])->label(), 'score' => (float) $row['score']],
            SuggestionField::Language->value => ['value' => $languageCode, 'label' => Languages::nameFor($languageCode) ?? $languageCode],
            SuggestionField::Summary->value => ['value' => $row['summary'], 'label' => $row['summary']],
        ];

        $triageLog = $this->sampleCall($workspace, AiFeature::TicketTriage, $ticket, $createdAt->addMinute(), $ticket->promptBlock(), [
            'category' => $category->name,
            'priority' => $row['priority'],
            'sentiment' => $row['sentiment'],
            'sentiment_score' => $row['score'],
            'language_code' => $languageCode,
            'language_name' => $aiValues[SuggestionField::Language->value]['label'],
            'summary' => $row['summary'],
        ], $index);

        $decidedAt = $this->notAfterNow($createdAt->addMinutes(25 + $index % 40));

        foreach ($aiValues as $field => $value) {
            [$decision, $finalValue] = $this->triageDecision($row, $field, $value, $categories);

            $this->suggestion($workspace, $ticket, AiFeature::TicketTriage, SuggestionField::from($field), $value, $decision, $finalValue, $triageLog, $decision === SuggestionDecision::Pending ? null : $agent, $decidedAt);

            if ($finalValue !== null) {
                $this->applyTicketValue($ticket, SuggestionField::from($field), $finalValue);
            }
        }

        $draft = $this->replyDraft($row, $firstName);
        $draftLog = $this->sampleCall($workspace, AiFeature::ReplyDraft, $ticket, $createdAt->addMinutes(2), $ticket->promptBlock(), ['text' => $draft], $index);

        if (! $hasReply) {
            $this->suggestion($workspace, $ticket, AiFeature::ReplyDraft, SuggestionField::Reply, ['value' => $draft, 'label' => $draft, 'sources' => $this->sourcesFor($row['cat'])], SuggestionDecision::Pending, null, $draftLog, null, null);
            $ticket->save();

            return;
        }

        $replyDecision = SuggestionDecision::from($row['reply'] ?? 'accepted');
        $sent = match ($replyDecision) {
            SuggestionDecision::Edited => str_replace("\n\nThe Oakline Home team", "\n\nThanks again for your patience.\n\nThe Oakline Home team", $draft),
            SuggestionDecision::Rejected => "Hi {$firstName},\n\nI've passed this to our team lead, who will call you today to sort it out personally. Sorry for the trouble.\n\n".($agent !== null ? $agent->name : 'The Oakline Home team'),
            default => $draft,
        };
        $repliedAt = $this->notAfterNow($createdAt->addMinutes(70 + ($index * 11) % 180));

        $this->message($ticket, MessageAuthorType::Agent, $agent, $sent, $repliedAt);
        $this->suggestion(
            $workspace, $ticket, AiFeature::ReplyDraft, SuggestionField::Reply,
            ['value' => $draft, 'label' => $draft, 'sources' => $this->sourcesFor($row['cat'])],
            $replyDecision,
            $replyDecision === SuggestionDecision::Rejected ? null : ['value' => $sent, 'label' => $sent],
            $draftLog, $agent, $repliedAt,
        );

        $ticket->last_activity_at = $repliedAt;
        $ticket->resolved_at = $status === TicketStatus::Resolved ? $repliedAt : null;
        $ticket->save();
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{value: mixed, label: string, score?: float}  $value
     * @param  array<string, TicketCategory>  $categories
     * @return array{0: SuggestionDecision, 1: array{value: mixed, label: string, score?: float, reason?: string, signals?: list<string>, sources?: list<string>, subject?: string}|null}
     */
    private function triageDecision(array $row, string $field, array $value, array $categories): array
    {
        $review = $row['review'] ?? 'accepted';

        if ($review === 'pending') {
            return [SuggestionDecision::Pending, null];
        }

        if ($review === 'rejected' && ($row['reject'] ?? null) === $field) {
            return [SuggestionDecision::Rejected, null];
        }

        if ($review === 'edited' && ($row['edit']['field'] ?? null) === $field) {
            $edited = match ($field) {
                SuggestionField::Priority->value => ['value' => $row['edit']['value'], 'label' => TicketPriority::from($row['edit']['value'])->label()],
                SuggestionField::Category->value => ['value' => $categories[$row['edit']['value']]->id, 'label' => $categories[$row['edit']['value']]->name],
                default => $value,
            };

            return [SuggestionDecision::Edited, $edited];
        }

        return [SuggestionDecision::Accepted, $value];
    }

    /**
     * @param  array{value: mixed, label: string, score?: float, reason?: string, signals?: list<string>, sources?: list<string>, subject?: string}  $value
     */
    private function applyTicketValue(Ticket $ticket, SuggestionField $field, array $value): void
    {
        match ($field) {
            SuggestionField::Category => $ticket->category_id = (int) $value['value'],
            SuggestionField::Priority => $ticket->priority = TicketPriority::from((string) $value['value']),
            SuggestionField::Sentiment => [$ticket->sentiment = Sentiment::from((string) $value['value']), $ticket->sentiment_score = (string) ($value['score'] ?? Sentiment::from((string) $value['value'])->defaultScore())],
            SuggestionField::Language => [$ticket->language = (string) $value['value'], $ticket->language_name = (string) $value['label']],
            SuggestionField::Summary => $ticket->summary = (string) $value['value'],
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function replyDraft(array $row, string $firstName): string
    {
        if (isset($row['reply_text'])) {
            return (string) $row['reply_text'];
        }

        return strtr(OaklineHomeSampleData::replyTemplates()[$row['cat']], [
            '{first}' => $firstName,
            '{answer}' => (string) ($row['answer'] ?? 'I\'ve checked with the team and will come back to you with the details today.'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function sourcesFor(string $categoryKey): array
    {
        return match ($categoryKey) {
            'delivery' => ['Delivery times', 'Late deliveries'],
            'damaged' => ['Damaged on arrival', 'Warranty'],
            'refunds' => ['Returns', 'Duplicate charges', 'Late deliveries'],
            'product' => ['Caring for solid oak', 'Assembly', 'Warranty'],
            default => ['Changes and cancellations', 'Delivery times'],
        };
    }

    /**
     * @param  array{admin: User|null, agent: User|null}  $people
     */
    private function seedLeads(Workspace $workspace, array $people): void
    {
        $sender = $people['admin'] ?? $people['agent'];
        $senderName = $sender !== null ? $sender->name : 'The Oakline Home team';

        foreach (OaklineHomeSampleData::leads() as $index => $row) {
            $createdAt = $this->moment((int) $row['days'], $index + 7);
            $firstName = Str::before($row['name'], ' ');
            $status = LeadStatus::from($row['status']);

            $lead = new Lead;
            $lead->workspace_id = $workspace->id;
            $lead->source = LeadSource::from($row['source']);
            $lead->status = $status;
            $lead->name = $row['name'];
            $lead->email = $row['email'];
            $lead->company = $row['company'];
            $lead->job_title = $row['title'];
            $lead->company_size = $row['size'];
            $lead->budget = $row['budget'];
            $lead->message = $row['message'];
            $lead->scoring_status = AiTaskStatus::Completed;
            $lead->follow_up_status = $row['project'] === null ? AiTaskStatus::Skipped : AiTaskStatus::Completed;
            $lead->created_at = $createdAt;
            $lead->updated_at = $createdAt;
            $lead->save();

            $score = LeadScore::from($row['score']);
            $suggested = ['value' => $score->value, 'label' => $score->label(), 'reason' => $row['reason'], 'signals' => $row['signals']];
            $scoreLog = $this->sampleCall($workspace, AiFeature::LeadScoring, $lead, $createdAt->addMinute(), $lead->promptBlock(), ['score' => $score->value, 'reason' => $row['reason'], 'signals' => $row['signals']], $index);
            $decidedAt = $this->notAfterNow($createdAt->addMinutes(40 + $index * 3));
            $review = (string) $row['scoreReview'];

            [$decision, $final] = match (true) {
                $review === 'pending' => [SuggestionDecision::Pending, null],
                $review === 'rejected' => [SuggestionDecision::Rejected, null],
                str_starts_with($review, 'edited:') => [SuggestionDecision::Edited, [
                    'value' => Str::after($review, 'edited:'),
                    'label' => LeadScore::from(Str::after($review, 'edited:'))->label(),
                    'reason' => "AI suggested {$score->label()}: {$row['reason']}",
                ]],
                default => [SuggestionDecision::Accepted, $suggested],
            };

            $this->suggestion($workspace, $lead, AiFeature::LeadScoring, SuggestionField::LeadScore, $suggested, $decision, $final, $scoreLog, $decision === SuggestionDecision::Pending ? null : $sender, $decidedAt);

            if ($final !== null) {
                $lead->score = LeadScore::from((string) $final['value']);
                $lead->score_reason = (string) $final['reason'];
            }

            if ($row['project'] !== null && $row['followUp'] !== 'none') {
                $subject = 'Oakline Trade for '.$row['project'];
                $body = OaklineHomeSampleData::followUpBody($firstName, $row['project'], $senderName);
                $draft = ['value' => $body, 'label' => $subject, 'subject' => $subject];
                $followUpLog = $this->sampleCall($workspace, AiFeature::LeadFollowUp, $lead, $createdAt->addMinutes(2), $lead->promptBlock(), ['subject' => $subject, 'body' => $body], $index);

                if ($row['followUp'] === 'sent') {
                    $sentBody = $index % 2 === 0 ? $body : str_replace('this week', 'on Thursday or Friday', $body);
                    $sentAt = $this->notAfterNow($createdAt->addHours(3));

                    $this->suggestion($workspace, $lead, AiFeature::LeadFollowUp, SuggestionField::FollowUp, $draft, $sentBody === $body ? SuggestionDecision::Accepted : SuggestionDecision::Edited, ['value' => $sentBody, 'label' => $subject, 'subject' => $subject], $followUpLog, $sender, $sentAt);

                    $lead->follow_up_subject = $subject;
                    $lead->follow_up_body = $sentBody;
                    $lead->follow_up_sent_at = $sentAt;
                    $lead->follow_up_sent_by = $sender?->id;
                } else {
                    $this->suggestion($workspace, $lead, AiFeature::LeadFollowUp, SuggestionField::FollowUp, $draft, SuggestionDecision::Pending, null, $followUpLog, null, null);
                }
            }

            $lead->save();
        }
    }

    /**
     * @param  array<string, TicketCategory>  $categories
     */
    private function seedBrief(Workspace $workspace, CarbonImmutable $date, array $categories): void
    {
        $collected = $this->briefData->collect($workspace, $date);
        $stats = $collected['stats'];
        // Group by the accepted category, or by the AI's suggestion for tickets nobody has reviewed yet.
        $dayTickets = $workspace->tickets()
            ->whereBetween('created_at', [$date->startOfDay(), $date->endOfDay()])
            ->with(['aiSuggestions' => fn (MorphMany $relation) => $relation->where('field', SuggestionField::Category)])
            ->get(['id', 'number', 'category_id']);
        $keysByCategoryId = collect($categories)->mapWithKeys(fn (TicketCategory $category, string $key): array => [$category->id => $key]);

        $themes = $dayTickets
            ->groupBy(fn (Ticket $ticket): int => (int) ($ticket->category_id ?? $ticket->aiSuggestions->first()?->suggested_value['value']))
            ->filter(fn (Collection $tickets, int|string $categoryId): bool => $tickets->count() >= 2 && $keysByCategoryId->has($categoryId))
            ->sortByDesc(fn (Collection $tickets): int => $tickets->count())
            ->map(fn (Collection $tickets, int|string $categoryId): array => [
                ...OaklineHomeSampleData::briefThemes()[$keysByCategoryId[$categoryId]],
                'tickets' => array_values($tickets->map(fn (Ticket $ticket): array => ['id' => $ticket->id, 'number' => $ticket->number])->all()),
            ])
            ->values();

        $urgentItems = array_slice(array_merge(
            array_map(function (array $ticket) use ($workspace): ?array {
                $model = $workspace->tickets()->where('number', $ticket['number'])->first(['id', 'number', 'subject']);

                return $model === null ? null : ['kind' => 'ticket', 'id' => $model->id, 'label' => "#{$model->number} {$model->subject}", 'reason' => 'Open and marked '.$ticket['priority'].'; the customer is waiting on a reply.'];
            }, array_slice($collected['urgent_open_tickets'], 0, 3)),
            array_map(fn (array $lead): array => ['kind' => 'lead', 'id' => $lead['id'], 'label' => $lead['company'] ? "{$lead['name']} ({$lead['company']})" : $lead['name'], 'reason' => 'Hot lead that has not been contacted yet.'], array_slice($collected['hot_leads_waiting'], 0, 2)),
        ), 0, 5);

        $topTheme = $themes->first();
        $dayName = $date->format('l');
        $headline = $topTheme !== null
            ? "{$topTheme['title']} led {$dayName}'s tickets"
            : ($stats['tickets'] > 0 ? "A steady {$dayName} for support" : "A quiet {$dayName}");
        $sentiment = $stats['average_sentiment'] === null ? '' : ' Average sentiment was '.($stats['average_sentiment'] < -0.2 ? 'negative' : ($stats['average_sentiment'] > 0.2 ? 'positive' : 'neutral')).' ('.sprintf('%+.2f', $stats['average_sentiment']).').';

        $brief = new DailyBrief;
        $brief->workspace_id = $workspace->id;
        $brief->brief_date = $date;
        $brief->trigger = BriefTrigger::Scheduled;
        $brief->generation_status = AiTaskStatus::Completed;
        $brief->stats = $stats;
        $brief->content = [
            'headline' => $headline,
            'summary' => "{$stats['tickets']} new tickets and {$stats['leads']} new leads, {$stats['hot_leads']} of them hot.{$sentiment} {$stats['urgent_open']} urgent tickets are still open.",
            'themes' => array_values($themes->all()),
            'urgent_items' => array_values(array_filter($urgentItems)),
        ];
        $brief->generated_at = $this->notAfterNow($date->addDay()->setTime(6, 2));
        $brief->save();

        $log = $this->sampleCall($workspace, AiFeature::DailyBrief, $brief, $brief->generated_at, 'Daily activity digest for '.$date->toFormattedDayDateString(), $brief->content, 3);
        $brief->forceFill(['ai_usage_log_id' => $log->id])->save();
    }

    private function message(Ticket $ticket, MessageAuthorType $authorType, ?User $user, string $body, CarbonImmutable $sentAt): void
    {
        $message = new TicketMessage;
        $message->ticket_id = $ticket->id;
        $message->author_type = $authorType;
        $message->user_id = $user?->id;
        $message->body = $body;
        $message->was_emailed = false;
        $message->sent_at = $sentAt;
        $message->created_at = $sentAt;
        $message->updated_at = $sentAt;
        $message->save();
    }

    /**
     * @param  array{value: mixed, label: string, score?: float, reason?: string, signals?: list<string>, sources?: list<string>, subject?: string}  $suggested
     * @param  array{value: mixed, label: string, score?: float, reason?: string, signals?: list<string>, sources?: list<string>, subject?: string}|null  $final
     */
    private function suggestion(Workspace $workspace, Model $subject, AiFeature $feature, SuggestionField $field, array $suggested, SuggestionDecision $decision, ?array $final, AiUsageLog $log, ?User $decider, ?CarbonImmutable $decidedAt): void
    {
        $suggestion = new AiSuggestion;
        $suggestion->workspace_id = $workspace->id;
        $suggestion->ai_usage_log_id = $log->id;
        $suggestion->subject()->associate($subject);
        $suggestion->feature = $feature;
        $suggestion->field = $field;
        $suggestion->suggested_value = $suggested;
        $suggestion->final_value = $final;
        $suggestion->decision = $decision;
        $suggestion->decided_by = $decision === SuggestionDecision::Pending ? null : $decider?->id;
        $suggestion->decided_at = $decision === SuggestionDecision::Pending ? null : $decidedAt;
        $suggestion->created_at = $log->created_at;
        $suggestion->updated_at = $decidedAt ?? $log->created_at;
        $suggestion->save();
    }

    /**
     * A logged AI call marked as sample data: realistic tokens and timing, zero cost.
     *
     * @param  array<string, mixed>  $output
     */
    private function sampleCall(Workspace $workspace, AiFeature $feature, Model $subject, ?CarbonImmutable $at, string $input, array $output, int $seed): AiUsageLog
    {
        $at ??= $this->now;

        $log = new AiUsageLog;
        $log->workspace_id = $workspace->id;
        $log->feature = $feature;
        $log->provider = self::SAMPLE_PROVIDER;
        $log->model = self::SAMPLE_MODEL;
        $log->input_tokens = 700 + ($seed * 37) % 900;
        $log->output_tokens = 90 + ($seed * 23) % 260;
        $log->estimated_cost_usd = '0';
        $log->duration_ms = 1400 + ($seed * 271) % 3600;
        $log->status = AiCallStatus::Succeeded;
        $log->input = [
            'system' => 'Sample data: this result was written when the demo was set up, so no AI call was made. New tickets and leads are analysed by the configured AI provider.',
            'messages' => [['role' => 'user', 'content' => $input]],
        ];
        $log->output = $output;
        $log->subject()->associate($subject);
        $log->created_at = $at;
        $log->updated_at = $at;
        $log->save();

        return $log;
    }

    /**
     * A stable, plausible timestamp for a record created $days ago (never in the future).
     */
    private function moment(int $days, int $index): CarbonImmutable
    {
        if ($days === 0) {
            return $this->now->subMinutes(20 + ($index * 37) % 360);
        }

        return $this->now->subDays($days)->setTime(8 + ($index * 5) % 11, ($index * 13) % 60);
    }

    private function notAfterNow(CarbonImmutable $moment): CarbonImmutable
    {
        return $moment->greaterThan($this->now) ? $this->now->subMinute() : $moment;
    }
}
