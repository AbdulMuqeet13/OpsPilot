<?php

namespace App\Providers;

use App\Enums\AiFailureReason;
use App\Models\Workspace;
use App\Services\AI\AiManager;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AiManager::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    /**
     * Named rate limiters used by the routes.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('demo-sandbox', fn (Request $request): Limit => Limit::perHour(config('demo.sandbox.max_per_ip_per_hour'))
            ->by($request->ip())
            ->response(fn () => back()->withErrors([
                'demo' => 'You have started several demos in the last hour. Please try again a little later.',
            ])));

        RateLimiter::for('public-forms', fn (Request $request): Limit => (config('demo.enabled')
            ? Limit::perHour(10)->by($request->ip())
            : Limit::perMinute(5)->by($request->ip()))
            ->response(fn () => back()->withErrors([
                'message' => "You've sent several messages in a short time. Please wait a little before sending another.",
            ])));

        RateLimiter::for('workspace-api', function (Request $request): Limit {
            $workspace = $request->attributes->get('workspace');
            $key = $workspace instanceof Workspace ? 'workspace:'.$workspace->id : 'ip:'.$request->ip();

            return config('demo.enabled') ? Limit::perMinute(10)->by($key) : Limit::perMinute(60)->by($key);
        });

        RateLimiter::for('ai', function (Request $request): array {
            $userKey = 'user:'.($request->user()->id ?? $request->ip());
            $tooManyRequests = fn () => response()->json(['message' => AiFailureReason::UsageLimitReached->userMessage()], 429);

            if (! config('demo.enabled')) {
                return [Limit::perMinute(60)->by($userKey)->response($tooManyRequests)];
            }

            return [
                Limit::perMinute(config('demo.ai_rate_limits.per_user_per_minute'))->by($userKey)->response($tooManyRequests),
                Limit::perHour(config('demo.ai_rate_limits.per_ip_per_hour'))->by('ip:'.$request->ip())->response($tooManyRequests),
            ];
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
