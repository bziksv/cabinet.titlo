<?php

return [
    /*
    | OAuth-клиент Google Cloud (Search Console API).
    | Redirect: {APP_URL}/google-search-console/callback
    | Scope: webmasters.readonly (+ email для статуса аккаунта).
    */
    'client_id' => env('GOOGLE_SEARCH_CONSOLE_CLIENT_ID', ''),
    'client_secret' => env('GOOGLE_SEARCH_CONSOLE_CLIENT_SECRET', ''),
    'redirect_uri' => env('GOOGLE_SEARCH_CONSOLE_REDIRECT_URI', null),
    'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
    'token_url' => 'https://oauth2.googleapis.com/token',
    'api_base' => 'https://www.googleapis.com/webmasters/v3',
    'userinfo_url' => 'https://www.googleapis.com/oauth2/v2/userinfo',
    'scope' => env(
        'GOOGLE_SEARCH_CONSOLE_SCOPE',
        'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/userinfo.email'
    ),
    'timeout' => 20,
];
