<?php

namespace App\Http\Middleware;

use App\Data\Brand;
use App\Enums\AiFailureReason;
use App\Services\AI\AiSpendGuard;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $workspace = $user?->workspace;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'roleLabel' => $user->role->label(),
                ] : null,
                'can' => [
                    'manageSettings' => $workspace !== null && $user->can('manageSettings', $workspace),
                ],
            ],
            'brand' => Brand::fromWorkspace($workspace)->toArray(),
            'aiPaused' => fn (): ?string => $user !== null && app(AiSpendGuard::class)->capReachedCached()
                ? AiFailureReason::SpendCapReached->userMessage()
                : null,
            'sandbox' => $workspace?->is_sandbox ? [
                'expiresAt' => $workspace->expires_at?->toIso8601String(),
            ] : null,
        ];
    }
}
