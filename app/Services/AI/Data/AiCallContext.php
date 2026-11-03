<?php

namespace App\Services\AI\Data;

use App\Enums\AiFeature;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Who and what an AI call is for, so it can be authorised, budgeted and logged.
 */
final readonly class AiCallContext
{
    public function __construct(
        public AiFeature $feature,
        public Workspace $workspace,
        public ?User $user = null,
        public ?Model $subject = null,
    ) {}
}
