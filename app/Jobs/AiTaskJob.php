<?php

namespace App\Jobs;

use App\Enums\AiFailureReason;
use App\Enums\AiTaskStatus;
use App\Services\AI\AiException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Shared lifecycle for queued AI work on a record: track progress in its
 * {task}_status / {task}_error columns, retry transient AI failures with
 * backoff, and leave a friendly message when the work can't be done.
 */
abstract class AiTaskJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Demo resets delete whole workspaces; jobs for records that no longer exist are dropped quietly.
     */
    public bool $deleteWhenMissingModels = true;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [20, 90];

    /**
     * The record whose status columns this job updates.
     */
    abstract protected function trackedModel(): Model;

    /**
     * The prefix of the record's *_status / *_error columns, for example "triage".
     */
    abstract protected function task(): string;

    abstract protected function run(): void;

    public function uniqueId(): string
    {
        return $this->task().':'.$this->trackedModel()->getMorphClass().':'.$this->trackedModel()->getKey();
    }

    public function handle(): void
    {
        $this->setStatus(AiTaskStatus::Processing);

        try {
            $this->run();
        } catch (AiException $exception) {
            if ($exception->reason === AiFailureReason::FeatureDisabled) {
                $this->setStatus(AiTaskStatus::Skipped);

                return;
            }

            if ($exception->isRetryable() && $this->attempts() < $this->tries) {
                $this->setStatus(AiTaskStatus::Pending);
                $this->release($this->backoff[$this->attempts() - 1] ?? 90);

                return;
            }

            $this->setStatus(AiTaskStatus::Failed, $exception->userMessage());

            return;
        }

        $this->setStatus(AiTaskStatus::Completed);
    }

    public function failed(?Throwable $exception): void
    {
        $this->setStatus(AiTaskStatus::Failed, 'Something went wrong while the AI was working on this. Please try again.');
    }

    protected function setStatus(AiTaskStatus $status, ?string $error = null): void
    {
        $this->trackedModel()->forceFill([
            $this->task().'_status' => $status,
            $this->task().'_error' => $error,
        ])->save();
    }
}
