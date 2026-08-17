<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Was '*' (any origin, from the framework's default fallback -- this
    // app never published its own config/cors.php). That meant any website
    // could read this API's responses from a visitor's browser. Restricted
    // to the actual known frontend origin(s); supports a comma-separated
    // list via CORS_ALLOWED_ORIGINS for staging/production, falling back to
    // FRONTEND_URL (already used elsewhere for password-reset links) so a
    // single env var change doesn't require touching two places.
    'allowed_origins' => array_filter(explode(',', env(
        'CORS_ALLOWED_ORIGINS',
        env('FRONTEND_URL', 'http://localhost:5173')
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Auth here is a Bearer token attached manually per-request (see
    // hris-fe-main/src/plugins/axios.js), not Sanctum's cookie-based SPA
    // session -- the frontend never calls /sanctum/csrf-cookie or sets
    // withCredentials. No cross-origin request ever needs to carry cookies.
    'supports_credentials' => false,

];
