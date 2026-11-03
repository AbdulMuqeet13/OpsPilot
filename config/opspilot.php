<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Brand
    |--------------------------------------------------------------------------
    |
    | Used on pages that are not tied to a workspace (landing page, sign in)
    | and as the starting point for every newly created workspace. Each
    | workspace can then re-skin itself from Settings → Branding.
    |
    */

    'default_brand' => [
        'name' => env('BRAND_NAME', 'OpsPilot'),
        'primary_color' => env('BRAND_PRIMARY_COLOR', '#4f46e5'),
        'support_email' => env('BRAND_SUPPORT_EMAIL', 'support@opspilot.test'),
    ],

    'logo' => [
        'disk' => env('BRAND_LOGO_DISK', 'public'),
        'max_kilobytes' => (int) env('BRAND_LOGO_MAX_KB', 1024),
    ],

];
