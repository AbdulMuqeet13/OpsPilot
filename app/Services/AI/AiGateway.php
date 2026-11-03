<?php

namespace App\Services\AI;

use App\Enums\AiCallStatus;
use App\Enums\AiFailureReason;
use App\Enums\AiFeature;
use App\Models\AiUsageLog;
use App\Models\Workspace;
use App\Services\AI\Data\AiCallContext;
use App\Services\AI\Data\ChatRequest;
use App\Services\AI\Data\ChatResult;
use App\Services\AI\Data\EmbeddingResult;
use App\Services\AI\Data\JsonSchema;
use App\Services\AI\Data\StructuredResult;
use App\Services\AI\Data\TokenUsage;
use Closure;
use Illuminate\Support\Str;
use Throwable;

/**
 * The only way the app talks to AI. Checks the feature switch and the
 * demo budget, adds the workspace's custom instructions, and writes one
 * ai_usage_logs row per call, whether it succeeds or fails.
 */
class AiGateway
{
    public function __construct(
        private AiManager $manager,
        private AiCostEstimator $costEstimator,
        private AiSpendGuard $spendGuard,
    ) {}

    public function chat(AiCallContext $context, ChatRequest $request): ChatResult
    {
        $request = $this->prepare($context, $request);
        $client = $this->manager->chatClient();

        [$result, $usageLogId] = $this->record($context, $client, $request->toLogArray(), function () use ($client, $request): array {
            $result = $client->chat($request);

            return [$result, $result->usage, $result->model, ['text' => $result->text]];
        });

        return $result->withUsageLogId($usageLogId);
    }

    /**
     * @param  Closure(string): void  $onTextDelta
     */
    public function streamChat(AiCallContext $context, ChatRequest $request, Closure $onTextDelta): ChatResult
    {
        $request = $this->prepare($context, $request);
        $client = $this->manager->chatClient();

        [$result, $usageLogId] = $this->record($context, $client, $request->toLogArray(), function () use ($client, $request, $onTextDelta): array {
            $result = $client->streamChat($request, $onTextDelta);

            return [$result, $result->usage, $result->model, ['text' => $result->text]];
        });

        return $result->withUsageLogId($usageLogId);
    }

    public function structured(AiCallContext $context, ChatRequest $request, JsonSchema $schema): StructuredResult
    {
        $request = $this->prepare($context, $request);
        $client = $this->manager->chatClient();

        [$result, $usageLogId] = $this->record($context, $client, [...$request->toLogArray(), 'schema' => $schema->name], function () use ($client, $request, $schema): array {
            $result = $client->structured($request, $schema);

            return [$result, $result->usage, $result->model, $result->data];
        });

        return $result->withUsageLogId($usageLogId);
    }

    /**
     * @param  list<string>  $texts
     */
    public function embed(AiCallContext $context, array $texts): EmbeddingResult
    {
        $this->ensureAvailable($context->workspace, $context->feature);
        $client = $this->manager->embeddingClient()
            ?? throw new AiException(AiFailureReason::NotConfigured, 'Embeddings are switched off (AI_EMBEDDING_PROVIDER is empty).');

        $logInput = ['texts' => count($texts), 'characters' => array_sum(array_map(mb_strlen(...), $texts))];

        [$result, $usageLogId] = $this->record($context, $client, $logInput, function () use ($client, $texts): array {
            $result = $client->embed($texts);

            return [$result, $result->usage, $result->model, ['dimensions' => count($result->vectors[0] ?? [])]];
        }, embedding: true);

        return $result->withUsageLogId($usageLogId);
    }

    public function embeddingsEnabled(): bool
    {
        try {
            return $this->manager->embeddingClient()?->supportsEmbeddings() ?? false;
        } catch (AiException) {
            return false;
        }
    }

    /**
     * @throws AiException when the feature is switched off or the demo budget is used up
     */
    public function ensureAvailable(Workspace $workspace, AiFeature $feature): void
    {
        if ($feature->isConfigurable() && ! $workspace->aiSettingFor($feature)->is_enabled) {
            throw new AiException(AiFailureReason::FeatureDisabled, "{$feature->label()} is disabled for workspace {$workspace->id}.");
        }

        if ($this->spendGuard->capReached()) {
            throw new AiException(AiFailureReason::SpendCapReached, 'Daily demo AI spend cap reached.');
        }
    }

    public function isAvailable(Workspace $workspace, AiFeature $feature): bool
    {
        try {
            $this->ensureAvailable($workspace, $feature);

            return true;
        } catch (AiException) {
            return false;
        }
    }

    private function prepare(AiCallContext $context, ChatRequest $request): ChatRequest
    {
        $this->ensureAvailable($context->workspace, $context->feature);

        if (! $context->feature->isConfigurable()) {
            return $request;
        }

        return $request->withAdditionalInstructions($context->workspace->aiSettingFor($context->feature)->custom_instructions);
    }

    /**
     * Run a provider call and log it, returning the call's result and the log id.
     *
     * @template TResult of ChatResult|StructuredResult|EmbeddingResult
     *
     * @param  array<string, mixed>  $logInput
     * @param  Closure(): array{0: TResult, 1: TokenUsage, 2: string, 3: array<string, mixed>}  $call
     * @return array{0: TResult, 1: int}
     */
    private function record(AiCallContext $context, AiClient $client, array $logInput, Closure $call, bool $embedding = false): array
    {
        $startedAt = hrtime(true);

        try {
            [$result, $usage, $model, $logOutput] = $call();
        } catch (Throwable $exception) {
            $aiException = $exception instanceof AiException
                ? $exception
                : new AiException(AiFailureReason::InvalidResponse, 'Unexpected error: '.$exception->getMessage(), $exception);

            if (! $exception instanceof AiException) {
                report($exception);
            }

            $this->writeLog($context, $client, $this->modelNameFor($client, $embedding), $logInput, null, new TokenUsage, $startedAt, $aiException);

            throw $aiException;
        }

        $log = $this->writeLog($context, $client, $model, $logInput, $logOutput, $usage, $startedAt);

        return [$result, $log->id];
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>|null  $output
     */
    private function writeLog(AiCallContext $context, AiClient $client, string $model, array $input, ?array $output, TokenUsage $usage, int|float $startedAt, ?AiException $failure = null): AiUsageLog
    {
        $log = new AiUsageLog;
        $log->workspace_id = $context->workspace->id;
        $log->user_id = $context->user?->id;
        $log->feature = $context->feature;
        $log->provider = $client->providerName();
        $log->model = $model;
        $log->input_tokens = $usage->inputTokens;
        $log->output_tokens = $usage->outputTokens;
        $log->estimated_cost_usd = (string) $this->costEstimator->estimate($model, $usage);
        $log->duration_ms = (int) round((hrtime(true) - $startedAt) / 1_000_000);
        $log->status = match ($failure?->reason) {
            null => AiCallStatus::Succeeded,
            AiFailureReason::Refused => AiCallStatus::Refused,
            default => AiCallStatus::Failed,
        };
        $log->failure_reason = $failure?->reason;
        $log->error = $failure ? Str::limit($failure->getMessage(), 2000) : null;
        $log->input = $input;
        $log->output = $output;

        if ($context->subject !== null) {
            $log->subject()->associate($context->subject);
        }

        $log->save();

        return $log;
    }

    private function modelNameFor(AiClient $client, bool $embedding): string
    {
        $configured = $embedding ? config('ai.embedding_model') : config('ai.model');

        return is_string($configured) && $configured !== '' ? $configured : 'not-configured';
    }
}
