<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Providers and Models
    |--------------------------------------------------------------------------
    |
    | Chat-style calls (triage, drafts, scoring, briefs) use AI_PROVIDER and
    | AI_MODEL. Embeddings are optional and can come from a different
    | provider; Anthropic does not offer an embeddings API, so pair
    | AI_PROVIDER=anthropic with AI_EMBEDDING_PROVIDER=openai, or leave the
    | embedding provider empty to send every knowledge snippet instead.
    |
    | Supported providers: "anthropic", "openai".
    |
    */

    'provider' => env('AI_PROVIDER', 'anthropic'),

    'model' => env('AI_MODEL'),

    'embedding_provider' => env('AI_EMBEDDING_PROVIDER') ?: null,

    'embedding_model' => env('AI_EMBEDDING_MODEL'),

    /*
    |--------------------------------------------------------------------------
    | Reliability
    |--------------------------------------------------------------------------
    |
    | Rate limits (429), overloads (529) and 5xx errors are retried with
    | exponential backoff, honouring the provider's retry-after header.
    |
    */

    'timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 60),

    'max_attempts' => (int) env('AI_MAX_ATTEMPTS', 3),

    'retry_base_delay_ms' => (int) env('AI_RETRY_BASE_DELAY_MS', 1000),

    'retry_max_delay_ms' => (int) env('AI_RETRY_MAX_DELAY_MS', 20000),

    'providers' => [

        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
            'api_version' => '2026-10-21',

            // low | medium | high | xhigh | max. Low keeps routine triage fast and cheap.
            'effort' => env('ANTHROPIC_EFFORT', 'low'),

            // On a safety refusal the API re-runs the request on a suitable model.
            // Only sent for models that support server-side fallbacks.
            'refusal_fallback' => (bool) env('ANTHROPIC_REFUSAL_FALLBACK', true),
            'refusal_fallback_models' => ['claude-fable-5-1', 'claude-opus-5-5', 'claude-opus-5', 'claude-sonnet-5-5'],
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'organization' => env('OPENAI_ORGANIZATION'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Pricing (USD per 1M tokens)
    |--------------------------------------------------------------------------
    |
    | Used to estimate the cost of every call in ai_usage_logs and to enforce
    | the demo's daily spend cap. Check your provider's pricing page when you
    | change models, or set AI_PRICE_INPUT_PER_MTOK / AI_PRICE_OUTPUT_PER_MTOK
    | to override the rate for AI_MODEL. Unknown models use the deliberately
    | high fallback rate so the spend cap errs on the safe side.
    |
    */

    'pricing' => [
        'claude-fable-5-1' => ['input' => 10.00, 'output' => 50.00],
        'claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00],
        'claude-opus-5' => ['input' => 5.00, 'output' => 25.00],
        'claude-sonnet-5-5' => ['input' => 2.00, 'output' => 10.00],
        'claude-haiku-5-5' => ['input' => 0.10, 'output' => 0.50],
        'gpt-4.1-mini' => ['input' => 0.40, 'output' => 1.60],
        'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        'text-embedding-3-small' => ['input' => 0.02, 'output' => 0.00],
        'text-embedding-3-large' => ['input' => 0.13, 'output' => 0.00],
    ],

    'price_override' => [
        'input' => env('AI_PRICE_INPUT_PER_MTOK'),
        'output' => env('AI_PRICE_OUTPUT_PER_MTOK'),
    ],

    'fallback_pricing' => ['input' => 10.00, 'output' => 50.00],

];
