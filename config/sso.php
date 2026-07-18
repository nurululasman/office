<?php

return [
    'base_url' => rtrim((string) env('OFFICE_SSO_BASE_URL', ''), '/'),
    'client_id' => env('OFFICE_SSO_CLIENT_ID'),
    'client_secret' => env('OFFICE_SSO_CLIENT_SECRET'),
    'redirect_uri' => env('OFFICE_SSO_REDIRECT_URI', env('APP_URL', 'http://localhost').'/auth/callback'),
    'scopes' => array_values(array_filter(explode(' ', (string) env('OFFICE_SSO_SCOPES', 'openid profile email')))),
    'tenant_id' => env('OFFICE_SSO_TENANT_ID'),
    'session_max_minutes' => (int) env('OFFICE_SSO_SESSION_MAX_MINUTES', 480),

    'paths' => [
        'authorize' => '/oauth/authorize',
        'token' => '/oauth/token',
        'profile' => '/api/v1/auth/me',
        'revoke' => '/oauth/revoke',
    ],
];
