<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, visitors can spin up private sandbox workspaces from the
    | landing page, public registration is closed, and the cost and abuse
    | guards below are enforced.
    |
    */

    'enabled' => (bool) env('DEMO_MODE', false),

    /*
    | The permanent showcase workspace for your own presentations. demo:reset
    | restores it nightly. Change the password before deploying publicly.
    */

    'showcase' => [
        'slug' => env('DEMO_SHOWCASE_SLUG', 'oakline-home'),
        'admin_email' => env('DEMO_SHOWCASE_ADMIN_EMAIL', 'maya@oaklinehome.example'),
        'agent_email' => env('DEMO_SHOWCASE_AGENT_EMAIL', 'theo@oaklinehome.example'),
        'password' => env('DEMO_SHOWCASE_PASSWORD', 'password'),
    ],

    'sandbox' => [
        'lifetime_hours' => (int) env('DEMO_SANDBOX_LIFETIME_HOURS', 24),
        'max_per_ip_per_hour' => (int) env('DEMO_SANDBOXES_PER_IP_PER_HOUR', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | AI Cost and Abuse Guards
    |--------------------------------------------------------------------------
    |
    | The spend cap is global (all sandboxes together) and is measured from
    | ai_usage_logs. Once reached, AI features pause with a friendly message
    | until midnight in the app's timezone.
    |
    */

    'daily_ai_spend_cap_usd' => (float) env('DEMO_DAILY_AI_SPEND_CAP_USD', 2.00),

    'ai_rate_limits' => [
        'per_user_per_minute' => (int) env('DEMO_AI_REQUESTS_PER_USER_PER_MINUTE', 10),
        'per_ip_per_hour' => (int) env('DEMO_AI_REQUESTS_PER_IP_PER_HOUR', 60),
    ],

    'uploads' => [
        'max_kilobytes' => (int) env('DEMO_MAX_UPLOAD_KB', 512),
        'max_files_per_user' => (int) env('DEMO_MAX_FILES_PER_USER', 5),
        'max_rows' => (int) env('DEMO_MAX_IMPORT_ROWS', 50),
    ],

];
